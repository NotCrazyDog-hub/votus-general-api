<?php

namespace App\Domains\News\Jobs\Concerns;

use App\Domains\News\Jobs\SummarizeNewsJob;
use App\Domains\News\Models\News;
use App\Domains\News\Models\NewsSource;
use App\Domains\News\Services\ArticleImageExtractor;
use App\Domains\News\Services\LinkNormalizer;
use App\Domains\News\Services\NewsImageValidator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Etapa comum aos Jobs de coleta (uma por fonte): recebe os itens do feed já
 * ordenados por prioridade e grava até $limit notícias NOVAS e VÁLIDAS.
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
trait PersistsValidNews
{
    /**
     * @param  array<int, array<string, mixed>>  $items  já ordenados por prioridade
     * @return array<string, int> contadores do ciclo (também vão pro log)
     */
    protected function persistNewValid(NewsSource $source, array $items, int $limit, LinkNormalizer $normalizer): array
    {
        $validator = app(NewsImageValidator::class);
        $extractor = app(ArticleImageExtractor::class);

        $stats = [
            'encontradas' => count($items),
            'duplicadas' => 0,
            'incompletas' => 0,
            NewsImageValidator::SEM_IMAGEM => 0,
            NewsImageValidator::INVALIDA => 0,
            NewsImageValidator::GENERICA => 0,
            NewsImageValidator::INACESSIVEL => 0,
            'persistidas' => 0,
            'erros' => 0,
        ];

        // Duplicatas já gravadas, carregadas de uma vez (2 queries no total)
        // em vez de 2 por item: a Agência Brasil soma ~180 itens em 9 feeds,
        // e cada ida ao Supabase tem latência de rede considerável.
        $batchLinks = [];
        $batchTitles = [];
        foreach ($items as $item) {
            if (!empty($item['url'])) {
                $batchLinks[] = $normalizer->normalize(trim((string) $item['url']));
            }
            if (!empty($item['title'])) {
                $batchTitles[] = mb_strtolower(trim((string) $item['title']));
            }
        }

        $existingLinks = array_flip(
            News::whereIn('link_normalizado', array_values(array_unique($batchLinks)))->pluck('link_normalizado')->all()
        );
        $existingTitles = [];
        foreach (array_chunk(array_values(array_unique($batchTitles)), 200) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            foreach (News::whereRaw("lower(title) in ({$placeholders})", $chunk)->pluck('title') as $existing) {
                $existingTitles[mb_strtolower(trim($existing))] = true;
            }
        }

        $seenThisCycle = [];

        // Cada item novo pode custar até 2 requisições HTTP (og:image + checagem
        // da imagem). Teto de itens NOVOS avaliados por ciclo pra o Job não se
        // arrastar quando a fonte tiver muita coisa sem foto.
        $maxImageChecks = max($limit * 4, 12);
        $imageChecks = 0;

        foreach ($items as $item) {
            if ($stats['persistidas'] >= $limit || $imageChecks >= $maxImageChecks) {
                break;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $url = trim((string) ($item['url'] ?? ''));

            if ($title === '' || $url === '') {
                $stats['incompletas']++;
                continue;
            }

            try {
                $normalizedLink = $normalizer->normalize($url);
                $normalizedTitle = mb_strtolower($title);

                // Duplicata: mesma regra de antes (link normalizado OU título
                // igual), agora também dentro do próprio lote — a Agência
                // Brasil repete a mesma matéria em várias editorias.
                if (
                    isset($seenThisCycle['link:' . $normalizedLink])
                    || isset($seenThisCycle['titulo:' . $normalizedTitle])
                    || isset($existingLinks[$normalizedLink])
                    || isset($existingTitles[$normalizedTitle])
                ) {
                    $stats['duplicadas']++;
                    continue;
                }

                $seenThisCycle['link:' . $normalizedLink] = true;
                $seenThisCycle['titulo:' . $normalizedTitle] = true;

                // Imagem: a do feed; se não vier, a capa oficial da matéria
                // (og:image). Nunca uma imagem inventada.
                $imageChecks++;
                $image = trim((string) ($item['image_url'] ?? ''));

                if ($image === '') {
                    $image = (string) $extractor->extract($url);
                }

                $invalidReason = $validator->invalidReason($image);

                if ($invalidReason !== null) {
                    $stats[$invalidReason]++;
                    continue;
                }

                $news = News::create([
                    'fonte_id' => $source->id,
                    'title' => $title,
                    'conteudo_original' => $item['conteudo_original'] ?? '',
                    'original_summary' => $item['original_summary'] ?? null,
                    'ai_summary' => '',
                    'status_resumo' => 'pendente',
                    'url' => $url,
                    'link_normalizado' => $normalizedLink,
                    'source' => $source->nome,
                    'category' => $item['category'] ?? null,
                    'eixo' => $item['categoria_slug'] ?? null,
                    'published_at' => $item['published_at'] ?? now(),
                    'relevance_score' => 5,
                    'keywords' => [],
                    'published' => false,
                    'image_url' => $image,
                ]);

                $stats['persistidas']++;
                SummarizeNewsJob::dispatch($news->id);
            } catch (UniqueConstraintViolationException) {
                // Outro processo (cron + botão do admin ao mesmo tempo) gravou
                // a mesma notícia entre a checagem e o insert — o índice único
                // de link_normalizado segurou. Conta como duplicata.
                $stats['duplicadas']++;
            } catch (Throwable $e) {
                $stats['erros']++;
                Log::error("[NEWS] {$source->slug}: falha ao processar item: {$e->getMessage()}", ['url' => $url]);
            }
        }

        Log::info(sprintf(
            '[NEWS] Fonte: %s | encontradas: %d | duplicadas: %d | sem imagem: %d | imagem inválida: %d | imagem genérica: %d | imagem inacessível: %d | incompletas: %d | erros: %d | persistidas: %d (limite %d)',
            $source->nome,
            $stats['encontradas'],
            $stats['duplicadas'],
            $stats[NewsImageValidator::SEM_IMAGEM],
            $stats[NewsImageValidator::INVALIDA],
            $stats[NewsImageValidator::GENERICA],
            $stats[NewsImageValidator::INACESSIVEL],
            $stats['incompletas'],
            $stats['erros'],
            $stats['persistidas'],
            $limit,
        ));

        return $stats;
    }
}
