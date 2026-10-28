<?php

namespace Tests\Feature\GovernmentPlans;

use App\Domains\Elections\Models\Candidate;
use App\Domains\GovernmentPlans\Models\GovernmentPlanComparison;
use App\Domains\GovernmentPlans\Models\GovernmentPlanProposal;
use App\Domains\GovernmentPlans\Models\GovernmentPlanTheme;
use App\Domains\GovernmentPlans\Services\GovernmentPlanSiteService;
use App\Domains\GovernmentPlans\Services\GroqGovernmentPlanService;
use App\Domains\GovernmentPlans\Services\PresidentialProposalImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MigratesTestSchema;
use Tests\TestCase;

class LulaSiteImportTest extends TestCase
{
    use MigratesTestSchema, RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return $this->testSchemaMigrateFreshUsing();
    }

    private Candidate $lula;

    private Candidate $flavio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lula = $this->candidate('LULA', 280002542548, 'PT');
        $this->flavio = $this->candidate('FLAVIO BOLSONARO', 280002551544, 'PL');

        Http::preventStrayRequests();
        Http::fake(['lula.com.br/*' => Http::response(file_get_contents(__DIR__ . '/../../Fixtures/lula-propostas.html'))]);
    }

    private function candidate(string $name, int $externalId, string $party): Candidate
    {
        return Candidate::create([
            'external_id' => $externalId, 'state' => 'BR', 'office_name' => 'PRESIDENTE',
            'civil_name' => $name, 'ballot_name' => $name, 'party_acronym' => $party, 'election_year' => 2026,
        ]);
    }

    private function aiProposal(Candidate $candidate, string $slug, string $title): GovernmentPlanProposal
    {
        $theme = GovernmentPlanTheme::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'position' => 0]);

        return GovernmentPlanProposal::create([
            'candidate_id' => $candidate->id, 'government_plan_theme_id' => $theme->id, 'title' => $title,
            'description' => 'Resumo da IA.', 'source_excerpt' => 'trecho', 'source_url' => 'https://exemplo.test',
            'fingerprint' => hash('sha256', $candidate->id . $title),
        ]);
    }

    public function test_dry_run_reads_the_site_but_writes_nothing(): void
    {
        $this->aiProposal($this->lula, 'saude', 'Resumo antigo');

        $this->artisan('import:lula-site-proposals', ['--dry-run' => true])
            ->expectsOutputToContain('47 propostas lidas do site (0 já gravadas); 47 novas a gravar; 1 anteriores a retirar de uso')
            ->assertSuccessful();

        $this->assertSame(1, GovernmentPlanProposal::count());
        $this->assertSame(1, GovernmentPlanProposal::active()->count());
    }

    public function test_import_replaces_the_ai_summaries_without_deleting_anything_and_is_idempotent(): void
    {
        $old = $this->aiProposal($this->lula, 'saude', 'Resumo antigo da IA');
        $flavioProposal = $this->aiProposal($this->flavio, 'saude', 'Proposta do Flávio');

        $this->artisan('import:lula-site-proposals')->assertSuccessful();

        // 47 literais em uso, a antiga continua no banco mas fora de uso.
        $this->assertSame(47, GovernmentPlanProposal::active()->where('candidate_id', $this->lula->id)->count());
        $this->assertSame(48, GovernmentPlanProposal::where('candidate_id', $this->lula->id)->count());
        $this->assertNotNull($old->fresh());
        $this->assertNotNull($old->fresh()->metadata['superseded_at']);
        $this->assertSame('import:lula-site-proposals', $old->fresh()->metadata['superseded_by']);

        // Outro candidato não é tocado.
        $this->assertNull($flavioProposal->fresh()->metadata);

        // Texto e título literais, com a origem registrada.
        $library = GovernmentPlanProposal::where('title', 'Fábrica de bibliotecas')->firstOrFail();
        $this->assertSame('literal', $library->extractionMethod());
        $this->assertSame('cultura', $library->theme->slug);
        $this->assertStringStartsWith('Bibliotecas nos condomínios', $library->description);
        $this->assertStringStartsWith('https://lula.com.br/propostas/#', $library->source_url);

        // Seções novas do site criam o tema; as existentes reaproveitam o do Votus.
        $this->assertNotNull(GovernmentPlanTheme::where('slug', 'mulheres')->first());
        $this->assertSame(1, GovernmentPlanTheme::where('slug', 'saude')->count());

        // Rodar de novo não duplica nada.
        $this->artisan('import:lula-site-proposals')->assertSuccessful();
        $this->assertSame(48, GovernmentPlanProposal::where('candidate_id', $this->lula->id)->count());
    }

    public function test_the_api_serves_only_the_literal_proposals_and_says_how_each_text_was_obtained(): void
    {
        $this->aiProposal($this->lula, 'saude', 'Resumo antigo da IA');
        $this->aiProposal($this->flavio, 'saude', 'Proposta do Flávio');
        $this->artisan('import:lula-site-proposals')->assertSuccessful();

        $proposals = collect($this->getJson('/api/presidential-proposal-comparison/proposals')->assertOk()->json('data.proposals'));

        $lula = $proposals->where('candidate_id', $this->lula->id);
        $this->assertCount(47, $lula);
        $this->assertSame(['literal'], $lula->pluck('extraction')->unique()->values()->all());
        $this->assertNull($lula->firstWhere('title', 'Resumo antigo da IA'));
        $this->assertSame('ai', $proposals->firstWhere('title', 'Proposta do Flávio')['extraction']);
    }

    public function test_the_ai_import_never_deletes_and_leaves_the_literal_proposals_alone(): void
    {
        $this->artisan('import:lula-site-proposals')->assertSuccessful();
        $literalIds = GovernmentPlanProposal::active()->where('candidate_id', $this->lula->id)->pluck('id')->sort()->values()->all();

        $oldFlavio = $this->aiProposal($this->flavio, 'saude', 'Proposta antiga do Flávio');
        $stale = GovernmentPlanComparison::create([
            'government_plan_theme_id' => GovernmentPlanTheme::where('slug', 'saude')->first()->id,
            'candidate_one_id' => min($this->lula->id, $this->flavio->id), 'candidate_two_id' => max($this->lula->id, $this->flavio->id),
            'summary' => 'Resumo velho', 'similarities' => [], 'differences' => [], 'candidate_summaries' => [],
        ]);
        $before = GovernmentPlanProposal::count();

        $this->mock(GovernmentPlanSiteService::class, fn ($mock) => $mock->shouldReceive('fetchPages')->andReturn([
            ['url' => 'https://flavio.test/plano', 'text' => 'Texto. Vamos criar o prontuário único. Fim.'],
        ]));
        $this->mock(GroqGovernmentPlanService::class, function ($mock) {
            $mock->shouldReceive('extract')->andReturn(['proposals' => [[
                'theme' => 'Saúde', 'title' => 'Prontuário único', 'description' => 'Criar o prontuário único.',
                'source_excerpt' => 'Vamos criar o prontuário único', 'source_reference' => null,
            ]]]);
            $mock->shouldReceive('compare')->andReturn([
                'summary' => 'Resumo novo', 'similarities' => ['a'], 'differences' => ['b'], 'candidate_summaries' => [],
            ]);
        });

        $result = app(PresidentialProposalImportService::class)->import();

        // Nada apagado: só cresceu (a nova do Flávio).
        $this->assertGreaterThanOrEqual($before, GovernmentPlanProposal::count());
        $this->assertNotNull($oldFlavio->fresh());
        $this->assertNotNull($oldFlavio->fresh()->metadata['superseded_at']); // saiu de uso, continua no banco
        $this->assertSame(1, GovernmentPlanProposal::active()->where('candidate_id', $this->flavio->id)->count());

        // Propostas literais do Lula intactas.
        $this->assertSame($literalIds, GovernmentPlanProposal::active()->where('candidate_id', $this->lula->id)->pluck('id')->sort()->values()->all());

        // Comparação atualizada no lugar (mesmo registro), não apagada e recriada.
        $this->assertSame('Resumo novo', $stale->fresh()->summary);
        $this->assertSame($stale->id, GovernmentPlanComparison::where('government_plan_theme_id', $stale->government_plan_theme_id)->value('id'));
        $this->assertGreaterThan(0, $result['comparisons']);
    }

    public function test_compare_stored_only_updates_comparisons(): void
    {
        $this->artisan('import:lula-site-proposals')->assertSuccessful();
        $this->aiProposal($this->flavio, 'saude', 'Proposta do Flávio');
        $proposalsBefore = GovernmentPlanProposal::count();

        $this->mock(GroqGovernmentPlanService::class, fn ($mock) => $mock->shouldReceive('compare')->andReturn([
            'summary' => 'Comparação', 'similarities' => [], 'differences' => [], 'candidate_summaries' => [],
        ]));

        $this->artisan('compare:presidential-plans')->assertSuccessful();

        $this->assertSame($proposalsBefore, GovernmentPlanProposal::count());
        $this->assertGreaterThan(0, GovernmentPlanComparison::count());
    }
}
