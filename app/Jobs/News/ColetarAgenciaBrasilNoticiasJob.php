<?php

namespace App\Jobs\News;

use App\Models\Fonte;
use App\Models\News;
use App\Services\News\AgenciaBrasilCollector;
use App\Services\News\LinkNormalizer;
use App\Services\News\NewsCategoryPriority;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ColetarAgenciaBrasilNoticiasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    /**
     * Teto de candidatos avaliados por fonte a cada ciclo — não é uma meta,
     * é só um limite de segurança pra não concentrar tudo numa fonte só (a
     * Agência Brasil tem 9 categorias) nem sobrecarregar a fila de resumo
     * (rate limited a 10/min). A meta de ~15 notícias relevantes por ciclo
     * (combinando as fontes) é decidida pelo filtro de conteúdo do resumo
     * (GroqSummarizerService), não aqui — este corte só garante candidatos
     * suficientes pra esse filtro trabalhar.
     */
    private const MAX_NOTICIAS_POR_CICLO = 30;

    public function __construct(public int $fonteId, public ?int $limite = null)
    {
        $this->onQueue('coleta');
    }

    public function handle(AgenciaBrasilCollector $collector, LinkNormalizer $normalizer): void
    {
        $fonte = Fonte::find($this->fonteId);

        if (!$fonte || !$fonte->ativa) {
            return;
        }

        $feeds = $fonte->feeds ?? [];

        if (empty($feeds)) {
            return;
        }

        $categoriasComErro = 0;
        $itensColetados = [];

        foreach ($feeds as $categoriaSlug => $feedUrl) {
            try {
                $itens = $collector->coletar($feedUrl);
            } catch (Throwable $e) {
                $categoriasComErro++;
                Log::error("[coleta] {$fonte->slug}/{$categoriaSlug} falhou: {$e->getMessage()}", [
                    'fonte_id' => $fonte->id,
                    'exception' => $e,
                ]);
                continue;
            }

            foreach ($itens as $item) {
                $item['categoria_slug'] = (string) $categoriaSlug;
                $itensColetados[] = $item;
            }
        }

        // Etapa 1 (filtro por categoria): prioriza editorias compatíveis com o
        // Votus (política, eleições, justiça, educação, saúde, economia...)
        // sobre editorias-catálogo tipo "geral"/"últimas notícias" — mas não
        // exclui essas últimas, porque às vezes trazem pauta relevante. A
        // aprovação de fato é decidida pela Etapa 2 (filtro por conteúdo),
        // no resumo de IA.
        usort($itensColetados, function (array $a, array $b) {
            $prioridadeA = NewsCategoryPriority::isPrioritaria($a['categoria_slug']) ? 0 : 1;
            $prioridadeB = NewsCategoryPriority::isPrioritaria($b['categoria_slug']) ? 0 : 1;

            if ($prioridadeA !== $prioridadeB) {
                return $prioridadeA <=> $prioridadeB;
            }

            return strcmp($b['published_at'] ?? '', $a['published_at'] ?? '');
        });

        foreach (array_slice($itensColetados, 0, $this->limite ?? self::MAX_NOTICIAS_POR_CICLO) as $item) {
            try {
                $this->persistirItem($fonte, $item['categoria_slug'], $item, $normalizer);
            } catch (Throwable $e) {
                Log::error("[coleta] falha ao persistir notícia de {$fonte->slug}/{$item['categoria_slug']}: {$e->getMessage()}", [
                    'url' => $item['url'] ?? null,
                    'exception' => $e,
                ]);
            }
        }

        if ($categoriasComErro > 0 && $categoriasComErro >= count($feeds)) {
            $fonte->registrarFalha("Todas as {$categoriasComErro} categorias falharam na última coleta.");
            return;
        }

        $fonte->registrarSucesso();
    }

    private function persistirItem(Fonte $fonte, string $categoriaSlug, array $item, LinkNormalizer $normalizer): void
    {
        if (empty($item['title']) || empty($item['url'])) {
            return;
        }

        $linkNormalizado = $normalizer->normalizar($item['url']);

        if (News::where('link_normalizado', $linkNormalizado)->exists()) {
            return;
        }

        if (News::whereRaw('lower(title) = ?', [mb_strtolower(trim($item['title']))])->exists()) {
            return;
        }

        $noticia = News::create([
            'fonte_id' => $fonte->id,
            'title' => $item['title'],
            'conteudo_original' => $item['conteudo_original'] ?? '',
            'original_summary' => $item['original_summary'] ?? null,
            'ai_summary' => '',
            'status_resumo' => 'pendente',
            'url' => $item['url'],
            'link_normalizado' => $linkNormalizado,
            'source' => $fonte->nome,
            'category' => $item['category'] ?? null,
            'eixo' => $categoriaSlug,
            'published_at' => $item['published_at'] ?? now(),
            'relevance_score' => 5,
            'keywords' => [],
            'published' => false,
            'image_url' => $item['image_url'] ?? null,
        ]);

        ResumirNoticiaJob::dispatch($noticia->id);
    }
}
