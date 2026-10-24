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
        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/completions'));
    }
}
