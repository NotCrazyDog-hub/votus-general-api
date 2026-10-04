<?php

namespace App\Domains\News\Jobs;

use App\Domains\News\Jobs\Concerns\PersistsValidNews;
use App\Domains\News\Models\NewsSource;
use App\Domains\News\Services\Collectors\AgenciaBrasilCollector;
use App\Domains\News\Services\LinkNormalizer;
use App\Domains\News\Services\NewsCategoryPriority;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CollectAgenciaBrasilNewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PersistsValidNews;

    // Maior que antes (120s): agora cada item novo pode exigir buscar a
    // og:image da matéria e checar se a imagem responde.
    public int $timeout = 300;

    public int $tries = 1;

    /**
     * Teto de notícias NOVAS e VÁLIDAS (sem duplicata, com imagem) gravadas
     * por ciclo quando o Job é despachado sem limite explícito. Antes era um
     * corte de 30 candidatos BRUTOS feito antes da deduplicação — o que fazia
     * o ciclo gastar o teto com notícias já existentes. O comando
     * news:collect reparte a meta de 15 por execução entre as fontes.
     * A publicação continua decidida pelo filtro de conteúdo do resumo.
     */
    private const MAX_NOTICIAS_POR_CICLO = 15;

    public function __construct(public int $sourceId, public ?int $limit = null)
    {
        $this->onQueue('coleta');
    }

    /**
     * Cron (12h) e botão do admin são independentes e podem cair juntos: a
     * mesma fonte nunca é coletada por dois workers ao mesmo tempo — o
     * segundo é descartado, a coleta em andamento já cobre.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("coleta-fonte-{$this->sourceId}"))->dontRelease()->expireAfter($this->timeout)];
    }

    public function handle(AgenciaBrasilCollector $collector, LinkNormalizer $normalizer): void
    {
        $source = NewsSource::find($this->sourceId);

        if (!$source || !$source->ativa) {
            return;
        }

        $feeds = $source->feeds ?? [];

        if (empty($feeds)) {
            return;
        }

        $categoriesWithError = 0;
        $collectedItems = [];

        foreach ($feeds as $categorySlug => $feedUrl) {
            try {
                $items = $collector->collect($feedUrl);
            } catch (Throwable $e) {
                $categoriesWithError++;
                Log::error("[NEWS] Fonte {$source->slug}/{$categorySlug} falhou: {$e->getMessage()}", [
                    'fonte_id' => $source->id,
                    'exception' => $e,
                ]);
                continue;
            }

            foreach ($items as $item) {
                $item['categoria_slug'] = (string) $categorySlug;
                $collectedItems[] = $item;
            }
        }

        // Etapa 1 (filtro por categoria): prioriza editorias compatíveis com o
        // Votus (política, eleições, justiça, educação, saúde, economia...)
        // sobre editorias-catálogo tipo "geral"/"últimas notícias" — mas não
        // exclui essas últimas, porque às vezes trazem pauta relevante. A
        // aprovação de fato é decidida pela Etapa 2 (filtro por conteúdo),
        // no resumo de IA.
        usort($collectedItems, function (array $a, array $b) {
            $priorityA = NewsCategoryPriority::isPriority($a['categoria_slug']) ? 0 : 1;
            $priorityB = NewsCategoryPriority::isPriority($b['categoria_slug']) ? 0 : 1;

            if ($priorityA !== $priorityB) {
                return $priorityA <=> $priorityB;
            }

            return strcmp($b['published_at'] ?? '', $a['published_at'] ?? '');
        });

        if ($categoriesWithError > 0 && $categoriesWithError >= count($feeds)) {
            $source->recordFailure("Todas as {$categoriesWithError} categorias falharam na última coleta.");
            return;
        }

        // Deduplica, valida imagem e grava até o limite de notícias novas —
        // ver Concerns\PersistsValidNews.
        $this->persistNewValid($source, $collectedItems, $this->limit ?? self::MAX_NOTICIAS_POR_CICLO, $normalizer);

        $source->recordSuccess();
    }
}
