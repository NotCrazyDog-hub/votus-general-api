<?php

namespace Tests\Feature;

use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.scheduler.token' => 'token-de-teste']);
    }

    public function test_it_can_create_a_news_article_with_a_valid_token(): void
    {
        $payload = [
            'title' => 'Nova notícia',
            'original_summary' => 'Resumo original',
            'ai_summary' => 'Resumo gerado por IA',
            'url' => 'https://example.com/noticia',
            'source' => 'Agência Brasil',
            'category' => 'Política',
            'published_at' => '2026-07-18 10:00:00',
            'relevance_score' => 8,
            'keywords' => ['Brasil', 'Política'],
            'published' => true,
        ];

        $response = $this->postJson('/api/news', $payload, [
            'X-Scheduler-Token' => 'token-de-teste',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Notícia criada');

        $this->assertDatabaseHas('news', [
            'url' => $payload['url'],
            'title' => $payload['title'],
        ]);
    }

    public function test_it_rejects_creating_a_news_article_without_a_valid_token(): void
    {
        $response = $this->postJson('/api/news', [
            'title' => 'Nova notícia',
            'ai_summary' => 'Resumo gerado por IA',
            'url' => 'https://example.com/noticia-sem-token',
            'published_at' => '2026-07-18 10:00:00',
            'relevance_score' => 8,
            'keywords' => ['Brasil'],
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseMissing('news', ['url' => 'https://example.com/noticia-sem-token']);
    }

    public function test_it_lists_only_published_news_ordered_by_publish_date(): void
    {
        News::factory()->create([
            'title' => 'Notícia antiga',
            'url' => 'https://example.com/antiga',
            'published_at' => now()->subDays(2),
            'published' => true,
        ]);

        News::factory()->create([
            'title' => 'Notícia recente',
            'url' => 'https://example.com/recente',
            'published_at' => now(),
            'published' => true,
        ]);

        News::factory()->create([
            'title' => 'Notícia ainda não publicada',
            'url' => 'https://example.com/rascunho',
            'published_at' => now(),
            'published' => false,
        ]);

        $response = $this->getJson('/api/news');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertSame('Notícia recente', $response->json('data.0.title'));
    }

    public function test_it_shows_a_single_news_article(): void
    {
        $news = News::factory()->create();

        $response = $this->getJson("/api/news/{$news->id}");

        $response->assertOk()->assertJsonPath('id', $news->id);
    }

    public function test_it_ignores_an_invalid_sort_column_instead_of_injecting_it_into_the_query(): void
    {
        News::factory()->create(['published' => true, 'title' => 'A']);
        News::factory()->create(['published' => true, 'title' => 'B']);

        // "sort_by"/"direction" viram identificadores de coluna direto no SQL;
        // sem whitelist, isso é um vetor de SQL injection via query string.
        $response = $this->getJson('/api/news?sort_by=' . urlencode('id); DROP TABLE news; --') . '&direction=invalido');

        $response->assertOk();
        $this->assertDatabaseCount('news', 2);
    }
}
