<?php

namespace Tests\Unit\GovernmentPlans;

use App\Domains\GovernmentPlans\Services\GovernmentPlanSiteService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GovernmentPlanSiteServiceTest extends TestCase
{
    public function test_it_collects_only_the_official_plan_page_and_its_allowed_internal_pages(): void
    {
        Http::fake([
            'candidate.example/plano' => Http::response('
                <main>
                    <h1>Plano de Governo</h1>
                    <p>Propostas gerais.</p>
                    <a href="/plano/educacao">Educação</a>
                    <a href="https://externo.example/plano">Fonte externa</a>
                </main>
            '),
            'candidate.example/plano/educacao' => Http::response('
                <main><h1>Educação</h1><p>Construir novas escolas.</p></main>
            '),
        ]);

        $pages = app(GovernmentPlanSiteService::class)->fetchPages([
            'url' => 'https://candidate.example/plano',
            'allowed_hosts' => ['candidate.example'],
            'allowed_path_prefixes' => ['/plano'],
            'max_pages' => 10,
        ]);

        $this->assertCount(2, $pages);
        $this->assertSame('https://candidate.example/plano', $pages[0]['url']);
        $this->assertSame('https://candidate.example/plano/educacao', $pages[1]['url']);
        $this->assertStringContainsString('Construir novas escolas.', $pages[1]['text']);
    }
}
