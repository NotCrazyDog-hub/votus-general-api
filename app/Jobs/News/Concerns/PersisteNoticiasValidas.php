<?php

namespace App\Jobs\News\Concerns;

use App\Jobs\News\ResumirNoticiaJob;
use App\Models\Fonte;
use App\Models\News;
use App\Services\News\ExtratorImagemArtigo;
use App\Services\News\LinkNormalizer;
use App\Services\News\ValidadorImagemNoticia;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Etapa comum aos Jobs de coleta (uma por fonte): recebe os itens do feed já
 * ordenados por prioridade e grava até $limite notícias NOVAS e VÁLIDAS.
 *
 * Ordem de cada item: dados mínimos → duplicata → imagem → grava → resumo.
 *
 * Antes, os Jobs cortavam os N primeiros itens do feed e só depois checavam
 * duplicata. Como o topo do feed muda pouco entre um ciclo e outro, esses N
 * já estavam quase todos no banco — o ciclo terminava sem nada novo (ex.: 24/09,
 * só 9 novas) enquanto notícias inéditas mais abaixo no feed eram ignoradas.
 * Agora o limite conta só o que foi de fato gravado, e o laço continua
 * descendo o feed até achar novas.
 */
trait PersisteNoticiasValidas
{
    /**
     * @param  array<int, array<string, mixed>>  $itens  já ordenados por prioridade
     * @return array<string, int> contadores do ciclo (também vão pro log)
     */
    protected function persistirNovasValidas(Fonte $fonte, array $itens, int $limite, LinkNormalizer $normalizer): array
    {
        $validador = app(ValidadorImagemNoticia::class);
        $extrator = app(ExtratorImagemArtigo::class);

        $stats = [
            'encontradas' => count($itens),
            'duplicadas' => 0,
            'incompletas' => 0,
            ValidadorImagemNoticia::SEM_IMAGEM => 0,
            ValidadorImagemNoticia::INVALIDA => 0,
            ValidadorImagemNoticia::GENERICA => 0,
            ValidadorImagemNoticia::INACESSIVEL => 0,
            'persistidas' => 0,
            'erros' => 0,
        ];

        // Duplicatas já gravadas, carregadas de uma vez (2 queries no total)
        // em vez de 2 por item: a Agência Brasil soma ~180 itens em 9 feeds,
        // e cada ida ao Supabase tem latência de rede considerável.
        $linksDoLote = [];
        $titulosDoLote = [];
        foreach ($itens as $item) {
            if (!empty($item['url'])) {
                $linksDoLote[] = $normalizer->normalizar(trim((string) $item['url']));
            }
            if (!empty($item['title'])) {
                $titulosDoLote[] = mb_strtolower(trim((string) $item['title']));
            }
        }

        $linksExistentes = array_flip(
            News::whereIn('link_normalizado', array_values(array_unique($linksDoLote)))->pluck('link_normalizado')->all()
        );
        $titulosExistentes = [];
        foreach (array_chunk(array_values(array_unique($titulosDoLote)), 200) as $bloco) {
            $marcadores = implode(',', array_fill(0, count($bloco), '?'));
            foreach (News::whereRaw("lower(title) in ({$marcadores})", $bloco)->pluck('title') as $existente) {
                $titulosExistentes[mb_strtolower(trim($existente))] = true;
            }
        }

        $vistosNesteCiclo = [];

        // Cada item novo pode custar até 2 requisições HTTP (og:image + checagem
        // da imagem). Teto de itens NOVOS avaliados por ciclo pra o Job não se
        // arrastar quando a fonte tiver muita coisa sem foto.
        $maxAvaliacoesDeImagem = max($limite * 4, 12);
        $avaliacoesDeImagem = 0;

        foreach ($itens as $item) {
            if ($stats['persistidas'] >= $limite || $avaliacoesDeImagem >= $maxAvaliacoesDeImagem) {
                break;
            }

            $titulo = trim((string) ($item['title'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if ($titulo === '' || $url === '') {
                $stats['incompletas']++;
                continue;
            }

            try {
                $linkNormalizado = $normalizer->normalizar($url);
                $tituloNormalizado = mb_strtolower($titulo);

                // Duplicata: mesma regra de antes (link normalizado OU título
                // igual), agora também dentro do próprio lote — a Agência
                // Brasil repete a mesma matéria em várias editorias.
                if (
                    isset($vistosNesteCiclo['link:' . $linkNormalizado])
                    || isset($vistosNesteCiclo['titulo:' . $tituloNormalizado])
                    || isset($linksExistentes[$linkNormalizado])
                    || isset($titulosExistentes[$tituloNormalizado])
                ) {
                    $stats['duplicadas']++;
                    continue;
                }

                $vistosNesteCiclo['link:' . $linkNormalizado] = true;
                $vistosNesteCiclo['titulo:' . $tituloNormalizado] = true;

                // Imagem: a do feed; se não vier, a capa oficial da matéria
                // (og:image). Nunca uma imagem inventada.
                $avaliacoesDeImagem++;
                $imagem = trim((string) ($item['image_url'] ?? ''));

                if ($imagem === '') {
                    $imagem = (string) $extrator->extrair($url);
                }

                $motivo = $validador->motivoInvalida($imagem);

                if ($motivo !== null) {
                    $stats[$motivo]++;
                    continue;
                }

                $noticia = News::create([
                    'fonte_id' => $fonte->id,
                    'title' => $titulo,
                    'conteudo_original' => $item['conteudo_original'] ?? '',
                    'original_summary' => $item['original_summary'] ?? null,
                    'ai_summary' => '',
                    'status_resumo' => 'pendente',
                    'url' => $url,
                    'link_normalizado' => $linkNormalizado,
                    'source' => $fonte->nome,
                    'category' => $item['category'] ?? null,
                    'eixo' => $item['categoria_slug'] ?? null,
                    'published_at' => $item['published_at'] ?? now(),
                    'relevance_score' => 5,
                    'keywords' => [],
                    'published' => false,
                    'image_url' => $imagem,
                ]);

                $stats['persistidas']++;
                ResumirNoticiaJob::dispatch($noticia->id);
            } catch (UniqueConstraintViolationException) {
                // Outro processo (cron + botão do admin ao mesmo tempo) gravou
                // a mesma notícia entre a checagem e o insert — o índice único
                // de link_normalizado segurou. Conta como duplicata.
                $stats['duplicadas']++;
            } catch (Throwable $e) {
                $stats['erros']++;
                Log::error("[NEWS] {$fonte->slug}: falha ao processar item: {$e->getMessage()}", ['url' => $url]);
            }
        }

        Log::info(sprintf(
            '[NEWS] Fonte: %s | encontradas: %d | duplicadas: %d | sem imagem: %d | imagem inválida: %d | imagem genérica: %d | imagem inacessível: %d | incompletas: %d | erros: %d | persistidas: %d (limite %d)',
            $fonte->nome,
            $stats['encontradas'],
            $stats['duplicadas'],
            $stats[ValidadorImagemNoticia::SEM_IMAGEM],
            $stats[ValidadorImagemNoticia::INVALIDA],
            $stats[ValidadorImagemNoticia::GENERICA],
            $stats[ValidadorImagemNoticia::INACESSIVEL],
            $stats['incompletas'],
            $stats['erros'],
            $stats['persistidas'],
            $limite,
        ));

        return $stats;
    }
}
