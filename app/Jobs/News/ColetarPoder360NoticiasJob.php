<?php

namespace App\Jobs\News;

use App\Models\Fonte;
use App\Models\News;
use App\Services\News\LinkNormalizer;
use App\Services\News\Poder360Collector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ColetarPoder360NoticiasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public int $fonteId)
    {
        $this->onQueue('coleta');
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

        foreach ($feeds as $categoriaSlug => $feedUrl) {
            try {
                $itens = $collector->coletar($feedUrl);
            } catch (Throwable $e) {
                Log::error("[coleta] {$fonte->slug}/{$categoriaSlug} falhou: {$e->getMessage()}", [
                    'fonte_id' => $fonte->id,
                    'exception' => $e,
                ]);
                $fonte->registrarFalha($e->getMessage());
                return;
            }

            foreach ($itens as $item) {
                try {
                    $this->persistirItem($fonte, (string) $categoriaSlug, $item, $normalizer);
                } catch (Throwable $e) {
                    Log::error("[coleta] falha ao persistir notícia de {$fonte->slug}/{$categoriaSlug}: {$e->getMessage()}", [
                        'url' => $item['url'] ?? null,
                        'exception' => $e,
                    ]);
                }
            }
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
