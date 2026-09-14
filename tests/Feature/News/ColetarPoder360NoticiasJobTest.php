<?php

namespace Tests\Feature\News;

use App\Jobs\News\ColetarPoder360NoticiasJob;
use App\Jobs\News\ResumirNoticiaJob;
use App\Models\Fonte;
use App\Models\News;
use App\Services\News\LinkNormalizer;
use App\Services\News\Poder360Collector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ColetarPoder360NoticiasJobTest extends TestCase
{
    use RefreshDatabase;

    private function feedXml(): string
    {
        return file_get_contents(base_path('tests/Fixtures/poder360-feed.xml'));
    }

    private function fonte(array $atributos = []): Fonte
    {
        return Fonte::factory()->create(array_merge([
            'slug' => 'poder360',
            'nome' => 'Poder360',
            'feeds' => ['geral' => 'https://exemplo.com/feed.xml'],
        ], $atributos));
    }

    public function test_it_persists_new_items_and_dispatches_a_summary_job_for_each(): void
    {
        Queue::fake();
        Http::fake(['https://exemplo.com/feed.xml' => Http::response($this->feedXml(), 200)]);

        $fonte = $this->fonte();

        (new ColetarPoder360NoticiasJob($fonte->id))->handle(
            app(Poder360Collector::class),
            app(LinkNormalizer::class),
        );

        $this->assertSame(2, News::count());
        Queue::assertPushed(ResumirNoticiaJob::class, 2);

        $noticia = News::first();
        $this->assertSame($fonte->id, $noticia->fonte_id);
        $this->assertSame('Poder360', $noticia->source);
        $this->assertNotEmpty($noticia->original_summary);
    }

    public function test_it_does_not_duplicate_news_on_a_second_run(): void
    {
        Queue::fake();
        Http::fake(['https://exemplo.com/feed.xml' => Http::response($this->feedXml(), 200)]);

        $fonte = $this->fonte();
        $job = new ColetarPoder360NoticiasJob($fonte->id);
        $collector = app(Poder360Collector::class);
        $normalizer = app(LinkNormalizer::class);

        $job->handle($collector, $normalizer);
        $job->handle($collector, $normalizer);

        $this->assertSame(2, News::count());
    }

    public function test_a_feed_failure_trips_the_circuit_breaker(): void
    {
        Queue::fake();
        Http::fake(['https://exemplo.com/feed.xml' => Http::response('erro interno', 500)]);

        $fonte = $this->fonte(['limite_falhas' => 2]);

        (new ColetarPoder360NoticiasJob($fonte->id))->handle(
            app(Poder360Collector::class),
            app(LinkNormalizer::class),
        );

        $fonte->refresh();
        $this->assertSame(0, News::count());
        $this->assertSame(1, $fonte->falhas_consecutivas);
        $this->assertTrue($fonte->ativa);
    }
}
