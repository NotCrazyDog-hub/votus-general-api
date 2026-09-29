<?php

namespace App\Jobs\News;

use App\Jobs\News\Concerns\PersisteNoticiasValidas;
use App\Models\Fonte;
use App\Services\News\LinkNormalizer;
use App\Services\News\NewsCategoryPriority;
use App\Services\News\Poder360Collector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ColetarPoder360NoticiasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, PersisteNoticiasValidas;

    // Maior que antes (120s): agora cada item novo pode exigir buscar a
    // og:image da matéria e checar se a imagem responde.
    public int $timeout = 300;

    public int $tries = 1;

    /**
     * Teto de notícias NOVAS e VÁLIDAS gravadas por ciclo quando o Job é
     * despachado sem limite explícito — ver ColetarAgenciaBrasilNoticiasJob.
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

    public function handle(Poder360Collector $collector, LinkNormalizer $normalizer): void
    {
        $fonte = Fonte::find($this->fonteId);

        if (!$fonte || !$fonte->ativa) {
            return;
        }

        $feeds = $fonte->feeds ?? [];

        if (empty($feeds)) {
            return;
        }

        $itensColetados = [];

        foreach ($feeds as $categoriaSlug => $feedUrl) {
            try {
                $itens = $collector->coletar($feedUrl);
            } catch (Throwable $e) {
                Log::error("[NEWS] Fonte {$fonte->slug}/{$categoriaSlug} falhou: {$e->getMessage()}", [
                    'fonte_id' => $fonte->id,
                    'exception' => $e,
                ]);
                $fonte->registrarFalha($e->getMessage());
                return;
            }

            foreach ($itens as $item) {
                $item['categoria_slug'] = (string) $categoriaSlug;
                $itensColetados[] = $item;
            }
        }

        // Etapa 1 (filtro por categoria): o Poder360 só tem um feed ("geral"),
        // mas cada item já vem com sua própria categoria no XML (ex: "Poder
        // Eleições", "Poder Justiça") — usamos essa, não a chave do feed, pra
        // priorizar editorias compatíveis com o Votus. A aprovação de fato é
        // decidida pela Etapa 2 (filtro por conteúdo), no resumo de IA.
        usort($itensColetados, function (array $a, array $b) {
            $prioridadeA = NewsCategoryPriority::isPrioritaria($a['category'] ?? null) ? 0 : 1;
            $prioridadeB = NewsCategoryPriority::isPrioritaria($b['category'] ?? null) ? 0 : 1;

            if ($prioridadeA !== $prioridadeB) {
                return $prioridadeA <=> $prioridadeB;
            }

            return strcmp($b['published_at'] ?? '', $a['published_at'] ?? '');
        });

        // Deduplica, valida imagem e grava até o limite de notícias novas —
        // ver Concerns\PersisteNoticiasValidas.
        $this->persistirNovasValidas($fonte, $itensColetados, $this->limite ?? self::MAX_NOTICIAS_POR_CICLO, $normalizer);

        $fonte->registrarSucesso();
    }
}
