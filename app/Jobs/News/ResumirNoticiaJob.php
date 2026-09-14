<?php

namespace App\Jobs\News;

use App\Models\News;
use App\Services\News\GroqSummarizerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResumirNoticiaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 90;

    public int $tries = 20;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $newsId)
    {
        $this->onQueue('resumo');
    }

    public function middleware(): array
    {
        return [new RateLimited('resumo-ia')];
    }

    /**
     * O rate limiter libera (release) o job de volta pra fila a cada vez que o limite
     * é atingido, e cada release consome uma tentativa de $tries. Por isso limitamos
     * pelo relógio (retryUntil) em vez de confiar só na contagem de tentativas.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(3);
    }

    public function handle(GroqSummarizerService $summarizer): void
    {
        $noticia = News::find($this->newsId);

        if (!$noticia || $noticia->status_resumo === 'concluido') {
            return;
        }

        $noticia->update([
            'status_resumo' => 'em_processamento',
            'ultima_tentativa_resumo_em' => now(),
        ]);

        try {
            $resultado = $summarizer->resumir($noticia->title, $noticia->conteudo_original ?? '');

            $noticia->update([
                'ai_summary' => $resultado['resumo'],
                'relevance_score' => $resultado['relevancia'],
                'keywords' => $resultado['palavras_chave'],
                'status_resumo' => 'concluido',
                'published' => true,
                'tentativas_resumo' => $noticia->tentativas_resumo + 1,
            ]);
        } catch (Throwable $e) {
            $noticia->update([
                'status_resumo' => 'falhou',
                'tentativas_resumo' => $noticia->tentativas_resumo + 1,
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        News::where('id', $this->newsId)->update(['status_resumo' => 'falhou']);

        Log::error("[resumo] notícia #{$this->newsId} falhou definitivamente: {$exception->getMessage()}", [
            'exception' => $exception,
        ]);
    }
}
