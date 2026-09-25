<?php

namespace App\Jobs\News;

use App\Jobs\News\Concerns\PersisteNoticiasValidas;
use App\Models\Fonte;
use App\Services\News\AgenciaBrasilCollector;
use App\Services\News\LinkNormalizer;
use App\Services\News\NewsCategoryPriority;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ColetarAgenciaBrasilNoticiasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PersisteNoticiasValidas;

    // Maior que antes (120s): agora cada item novo pode exigir buscar a
    // og:image da matéria e checar se a imagem responde.
    public int $timeout = 300;

    public int $tries = 1;

    /**
     * Teto de notícias NOVAS e VÁLIDAS (sem duplicata, com imagem) gravadas
     * por ciclo quando o Job é despachado sem limite explícito. Antes era um
     * corte de 30 candidatos BRUTOS feito antes da deduplicação — o que fazia
     * o ciclo gastar o teto com notícias já existentes. O comando
     * noticias:coletar reparte a meta de 15 por execução entre as fontes.
     * A publicação continua decidida pelo filtro de conteúdo do resumo.
     */
    private const MAX_NOTICIAS_POR_CICLO = 15;

    public function __construct(public int $fonteId, public ?int $limite = null)
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
        return [(new WithoutOverlapping("coleta-fonte-{$this->fonteId}"))->dontRelease()->expireAfter($this->timeout)];
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
                Log::error("[NEWS] Fonte {$fonte->slug}/{$categoriaSlug} falhou: {$e->getMessage()}", [
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

        if ($categoriasComErro > 0 && $categoriasComErro >= count($feeds)) {
            $fonte->registrarFalha("Todas as {$categoriasComErro} categorias falharam na última coleta.");
            return;
        }

        // Deduplica, valida imagem e grava até o limite de notícias novas —
        // ver Concerns\PersisteNoticiasValidas.
        $this->persistirNovasValidas($fonte, $itensColetados, $this->limite ?? self::MAX_NOTICIAS_POR_CICLO, $normalizer);

        $fonte->registrarSucesso();
    }
}
