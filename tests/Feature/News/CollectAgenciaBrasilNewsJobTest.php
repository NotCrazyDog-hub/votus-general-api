<?php

namespace Tests\Feature\News;

use App\Domains\News\Jobs\CollectAgenciaBrasilNewsJob;
use App\Domains\News\Jobs\SummarizeNewsJob;
use App\Domains\News\Models\NewsSource;
use App\Domains\News\Models\News;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CollectAgenciaBrasilNewsJobTest extends NewsTestCase
{

    private function feedXml(): string
    {
        return file_get_contents(base_path('tests/Fixtures/agencia-brasil-feed.xml'));
    }

    public function test_it_persists_new_items_and_dispatches_a_summary_job_for_each(): void
    {
        Queue::fake();
        Http::fake([
            'https://exemplo.com/feed.xml' => Http::response($this->feedXml(), 200),
            // Regra nova: só grava com imagem que responde como imagem.
            'https://imagens.ebc.com.br/*' => Http::response('', 200, ['Content-Type' => 'image/png']),
        ]);

        $source = NewsSource::factory()->create();

        (new CollectAgenciaBrasilNewsJob($source->id))->handle(
            app(\App\Services\News\AgenciaBrasilCollector::class),
            app(\App\Services\News\LinkNormalizer::class),
        );

        $this->assertSame(2, News::count());
        Queue::assertPushed(SummarizeNewsJob::class, 2);

        $noticia = News::first();
        $this->assertSame('pendente', $noticia->status_resumo);
        $this->assertFalse($noticia->published);
        $this->assertSame($source->id, $noticia->fonte_id);
        $this->assertSame('ultimasnoticias', $noticia->eixo);

        $source->refresh();
        $this->assertSame(0, $source->falhas_consecutivas);
        $this->assertNotNull($source->ultima_coleta_em);
    }

    public function test_it_does_not_duplicate_news_on_a_second_run(): void
    {
        Queue::fake();
        Http::fake([
            'https://exemplo.com/feed.xml' => Http::response($this->feedXml(), 200),
            // Regra nova: só grava com imagem que responde como imagem.
            'https://imagens.ebc.com.br/*' => Http::response('', 200, ['Content-Type' => 'image/png']),
        ]);

        $source = NewsSource::factory()->create();
        $job = new CollectAgenciaBrasilNewsJob($source->id);
        $collector = app(\App\Services\News\AgenciaBrasilCollector::class);
        $normalizer = app(\App\Services\News\LinkNormalizer::class);

        $job->handle($collector, $normalizer);
        $job->handle($collector, $normalizer);

        $this->assertSame(2, News::count());
    }

    public function test_a_failing_source_does_not_stop_other_sources_and_trips_the_circuit_breaker(): void
    {
        Queue::fake();
        Http::fake(['https://exemplo.com/feed.xml' => Http::response('erro interno', 500)]);

        $source = NewsSource::factory()->create(['limite_falhas' => 2]);

        (new CollectAgenciaBrasilNewsJob($source->id))->handle(
            app(\App\Services\News\AgenciaBrasilCollector::class),
            app(\App\Services\News\LinkNormalizer::class),
        );

        $source->refresh();
        $this->assertSame(0, News::count());
        $this->assertSame(1, $source->falhas_consecutivas);
        $this->assertTrue($source->ativa);
        $this->assertNotNull($source->ultima_falha_em);

        // segunda falha consecutiva atinge o limite e desativa a fonte
        (new CollectAgenciaBrasilNewsJob($source->id))->handle(
            app(\App\Services\News\AgenciaBrasilCollector::class),
            app(\App\Services\News\LinkNormalizer::class),
        );

        $source->refresh();
        $this->assertSame(2, $source->falhas_consecutivas);
        $this->assertFalse($source->ativa);
        $this->assertNotNull($source->desativada_em);
    }

    public function test_an_inactive_source_is_skipped(): void
    {
        Http::fake([
            'https://exemplo.com/feed.xml' => Http::response($this->feedXml(), 200),
            // Regra nova: só grava com imagem que responde como imagem.
            'https://imagens.ebc.com.br/*' => Http::response('', 200, ['Content-Type' => 'image/png']),
        ]);

        $source = NewsSource::factory()->create(['ativa' => false]);

        (new CollectAgenciaBrasilNewsJob($source->id))->handle(
            app(\App\Services\News\AgenciaBrasilCollector::class),
            app(\App\Services\News\LinkNormalizer::class),
        );

        $this->assertSame(0, News::count());
        Http::assertNothingSent();
    }
}
