<?php

namespace Tests\Unit\GovernmentPlans;

use App\Domains\GovernmentPlans\Services\GroqGovernmentPlanService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqGovernmentPlanServiceTest extends TestCase
{
    public function test_it_extracts_only_the_structured_proposals_returned_by_groq(): void
    {
        config(['services.groq.api_keys' => ['test-key']]);
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode([
                        'proposals' => [[
                            'theme' => 'Educação',
                            'title' => 'Tempo integral',
                            'description' => 'Amplia escolas de tempo integral.',
                            'source_excerpt' => 'ampliar escolas integrais',
                            'source_reference' => 'p. 10',
                        ]],
                    ])]],
                ],
            ]),
        ]);

        $result = app(GroqGovernmentPlanService::class)->extract('Candidata', 'Trecho do plano.');

        $this->assertSame('Educação', $result['proposals'][0]['theme']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/completions')
            && $request['reasoning_effort'] === 'low');
    }

    public function test_it_asks_the_model_to_reuse_the_known_themes(): void
    {
        config(['services.groq.api_keys' => ['test-key']]);
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['proposals' => []])]]],
        ])]);

        app(GroqGovernmentPlanService::class)->extract('Candidata', 'Trecho.', ['Saúde', 'Segurança']);

        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Saúde; Segurança'));
    }

    public function test_the_comparison_must_be_written_in_portuguese(): void
    {
        config(['services.groq.api_keys' => ['test-key']]);
        Http::fake(['api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['summary' => 'Resumo.'])]]],
        ])]);

        app(GroqGovernmentPlanService::class)->compare('Saúde', ['A' => [], 'B' => []]);

        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'português do Brasil'));
    }

    public function test_it_waits_and_retries_when_every_key_is_rate_limited(): void
    {
        config(['services.groq.api_keys' => ['key-1', 'key-2']]);
        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '7'])
                ->push(['error' => ['code' => 'rate_limit_exceeded']], 429, ['Retry-After' => '7'])
                ->push(['choices' => [['message' => ['content' => json_encode(['proposals' => []])]]]]),
        ]);

        $service = new class extends GroqGovernmentPlanService {
            public array $pauses = [];

            protected function pause(int $seconds): void
            {
                $this->pauses[] = $seconds;
            }
        };

        $this->assertSame(['proposals' => []], $service->extract('Candidata', 'Trecho.'));
        $this->assertSame([7], $service->pauses);
        Http::assertSentCount(3);
    }
}
