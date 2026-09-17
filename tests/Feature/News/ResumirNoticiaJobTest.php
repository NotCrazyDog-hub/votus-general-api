<?php

namespace Tests\Feature\News;

use App\Jobs\News\ResumirNoticiaJob;
use App\Models\News;
use App\Services\News\GroqSummarizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResumirNoticiaJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.groq.api_keys' => ['chave-1', 'chave-2', 'chave-3']]);
    }

    public function test_it_summarizes_a_pending_news_item_and_publishes_it(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'resumo' => 'Um resumo objetivo da notícia.',
                        'relevancia' => 8,
                        'relevante_votus' => true,
                        'palavras_chave' => ['a', 'b'],
                    ])]],
                ],
            ], 200),
        ]);

        $noticia = News::factory()->create([
            'status_resumo' => 'pendente',
            'ai_summary' => '',
            'published' => false,
        ]);

        (new ResumirNoticiaJob($noticia->id))->handle(app(GroqSummarizerService::class));

        $noticia->refresh();
        $this->assertSame('concluido', $noticia->status_resumo);
        $this->assertSame('Um resumo objetivo da notícia.', $noticia->ai_summary);
        $this->assertSame(8, $noticia->relevance_score);
        $this->assertTrue($noticia->published);
    }

    public function test_it_summarizes_but_does_not_publish_a_news_item_outside_the_votus_scope(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'resumo' => '',
                        'relevancia' => 1,
                        'relevante_votus' => false,
                        'palavras_chave' => [],
                    ])]],
                ],
            ], 200),
        ]);

        $noticia = News::factory()->create([
            'status_resumo' => 'pendente',
            'ai_summary' => '',
            'published' => false,
        ]);

        (new ResumirNoticiaJob($noticia->id))->handle(app(GroqSummarizerService::class));

        $noticia->refresh();
        $this->assertSame('concluido', $noticia->status_resumo);
        $this->assertFalse($noticia->published);
    }

    public function test_a_summary_failure_keeps_the_news_row_and_marks_it_as_failed(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $noticia = News::factory()->create([
            'status_resumo' => 'pendente',
            'ai_summary' => '',
            'published' => false,
        ]);

        try {
            (new ResumirNoticiaJob($noticia->id))->handle(app(GroqSummarizerService::class));
            $this->fail('Esperava uma exceção quando todas as chaves da Groq falham.');
        } catch (\Throwable) {
            // esperado: a exceção é relançada para a fila decidir o retry
        }

        $this->assertDatabaseHas('news', ['id' => $noticia->id]);
        $noticia->refresh();
        $this->assertSame('falhou', $noticia->status_resumo);
        $this->assertFalse($noticia->published);
    }

    public function test_the_summarizer_falls_back_to_the_next_key_when_one_fails(): void
    {
        $tentativas = 0;

        Http::fake(function ($request) use (&$tentativas) {
            $tentativas++;

            if ($request->header('Authorization')[0] === 'Bearer chave-1') {
                return Http::response(['error' => ['message' => 'rate limit']], 429);
            }

            return Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'resumo' => 'Resumo via chave de backup.',
                        'relevancia' => 5,
                        'palavras_chave' => [],
                    ])]],
                ],
            ], 200);
        });

        $resultado = (new GroqSummarizerService())->resumir('Título fixo para hash', 'Conteúdo de teste');

        $this->assertSame('Resumo via chave de backup.', $resultado['resumo']);
        $this->assertGreaterThanOrEqual(1, $tentativas);
    }
}
