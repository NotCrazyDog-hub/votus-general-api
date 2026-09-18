<?php

namespace Tests\Feature\Explanations;

use App\Jobs\Explanations\GerarConteudoExplicacaoJob;
use App\Models\Explanation;
use App\Models\TrustedSource;
use App\Services\Explanations\ExplanationSourceFetcher;
use App\Services\Explanations\GroqExplanationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GerarConteudoExplicacaoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.groq.api_keys' => ['chave-1']]);
    }

    private function quizValido(): array
    {
        $pergunta = [
            'question' => 'Pergunta de teste?',
            'explanation' => 'Porque sim.',
            'options' => [
                ['text' => 'A', 'correct' => true],
                ['text' => 'B', 'correct' => false],
                ['text' => 'C', 'correct' => false],
                ['text' => 'D', 'correct' => false],
            ],
        ];

        return array_fill(0, 5, $pergunta);
    }

    private function criarExplicacaoComFonte(): Explanation
    {
        $fonte = TrustedSource::create(['name' => 'Fonte X', 'domain' => 'fontex.com', 'is_active' => true]);

        $explanation = Explanation::create([
            'title' => 'Câmara dos Deputados',
            'slug' => 'camara-dos-deputados-' . uniqid(),
            'question_title' => 'Você sabe o que é a Câmara dos Deputados?',
            'category' => 'Órgãos e instituições',
            'status' => 'generating',
        ]);

        $explanation->sources()->create([
            'trusted_source_id' => $fonte->id,
            'source_name' => $fonte->name,
            'source_url' => 'https://fontex.com/materia-sobre-camara',
            'source_domain' => $fonte->domain,
        ]);

        return $explanation;
    }

    public function test_it_fetches_the_source_and_generates_content_via_groq_on_success(): void
    {
        Http::fake([
            'fontex.com/*' => Http::response('<html><body><p>A Câmara dos Deputados é uma casa legislativa.</p></body></html>', 200),
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'explanation' => [
                            'summary' => 'Resumo.',
                            'what_is' => 'O que é.',
                            'purpose' => 'Pra que serve.',
                            'practical_role' => 'Papel prático.',
                            'why_it_matters' => 'Por que importa.',
                            'citizen_impact' => 'Impacto no cidadão.',
                            'example' => 'Um exemplo.',
                        ],
                        'quiz' => $this->quizValido(),
                    ])]],
                ],
            ], 200),
        ]);

        $explanation = $this->criarExplicacaoComFonte();

        (new GerarConteudoExplicacaoJob($explanation->id))->handle(
            app(ExplanationSourceFetcher::class),
            app(GroqExplanationService::class)
        );

        $explanation->refresh();
        $this->assertSame('review', $explanation->status);
        $this->assertSame('Resumo.', $explanation->summary);
        $this->assertNull($explanation->generation_error);
        $this->assertSame(5, $explanation->quizQuestions()->count());
        $this->assertSame(4, $explanation->quizQuestions()->first()->options()->count());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'fontex.com'));
    }

    public function test_it_marks_as_failed_when_the_source_url_fails_to_load(): void
    {
        Http::fake([
            'fontex.com/*' => Http::response('erro', 500),
        ]);

        $explanation = $this->criarExplicacaoComFonte();

        (new GerarConteudoExplicacaoJob($explanation->id))->handle(
            app(ExplanationSourceFetcher::class),
            app(GroqExplanationService::class)
        );

        $explanation->refresh();
        $this->assertSame('failed', $explanation->status);
        $this->assertNotNull($explanation->generation_error);
        $this->assertSame(0, $explanation->quizQuestions()->count());
    }

    public function test_it_marks_as_failed_when_groq_returns_an_invalid_quiz(): void
    {
        Http::fake([
            'fontex.com/*' => Http::response('<p>Conteúdo real da fonte.</p>', 200),
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'explanation' => ['summary' => 'Resumo.'],
                        'quiz' => array_fill(0, 3, ['question' => 'Q?', 'options' => []]),
                    ])]],
                ],
            ], 200),
        ]);

        $explanation = $this->criarExplicacaoComFonte();

        (new GerarConteudoExplicacaoJob($explanation->id))->handle(
            app(ExplanationSourceFetcher::class),
            app(GroqExplanationService::class)
        );

        $explanation->refresh();
        $this->assertSame('failed', $explanation->status);
        $this->assertNotNull($explanation->generation_error);
    }

    public function test_it_fails_immediately_when_there_are_no_sources(): void
    {
        $explanation = Explanation::create([
            'title' => 'Sem fonte',
            'slug' => 'sem-fonte',
            'question_title' => 'Pergunta?',
            'category' => 'Eleições e voto',
            'status' => 'generating',
        ]);

        (new GerarConteudoExplicacaoJob($explanation->id))->handle(
            app(ExplanationSourceFetcher::class),
            app(GroqExplanationService::class)
        );

        $explanation->refresh();
        $this->assertSame('failed', $explanation->status);
        $this->assertStringContainsString('fonte', $explanation->generation_error);
    }
}
