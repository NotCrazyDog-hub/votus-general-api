<?php

namespace Tests\Feature\GovernmentPlans;

use App\Domains\Elections\Models\Candidate;
use App\Domains\GovernmentPlans\Models\GovernmentPlanComparison;
use App\Domains\GovernmentPlans\Models\GovernmentPlanProposal;
use App\Domains\GovernmentPlans\Models\GovernmentPlanTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MigratesTestSchema;
use Tests\TestCase;

class PresidentialProposalComparisonEndpointsTest extends TestCase
{
    use MigratesTestSchema, RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return $this->testSchemaMigrateFreshUsing();
    }

    public function test_it_lists_themes_and_exposes_a_verifiable_comparison(): void
    {
        $lula = $this->candidate('Lula', 280002542548, 'PT');
        $flavio = $this->candidate('Flávio Bolsonaro', 280002551544, 'PL');
        $theme = GovernmentPlanTheme::create(['slug' => 'educacao', 'name' => 'Educação']);

        GovernmentPlanProposal::create([
            'candidate_id' => $lula->id,
            'government_plan_theme_id' => $theme->id,
            'title' => 'Ampliar escolas de tempo integral',
            'description' => 'Propõe ampliar a oferta de educação em tempo integral.',
            'source_excerpt' => 'ampliaremos a educação em tempo integral',
            'source_reference' => 'p. 12',
            'source_url' => 'https://example.test/lula.pdf',
            'fingerprint' => str_repeat('a', 64),
        ]);
        GovernmentPlanProposal::create([
            'candidate_id' => $flavio->id,
            'government_plan_theme_id' => $theme->id,
            'title' => 'Valorizar a carreira docente',
            'description' => 'Propõe medidas para a carreira de professores.',
            'source_url' => 'https://example.test/flavio.pdf',
            'fingerprint' => str_repeat('b', 64),
        ]);
        GovernmentPlanComparison::create([
            'government_plan_theme_id' => $theme->id,
            'candidate_one_id' => min($lula->id, $flavio->id),
            'candidate_two_id' => max($lula->id, $flavio->id),
            'summary' => 'Ambos tratam de educação, com focos distintos.',
            'similarities' => ['Os dois planos apresentam medidas para educação.'],
            'differences' => ['Um prioriza tempo integral; o outro, carreira docente.'],
            'candidate_summaries' => ['Lula' => 'Tempo integral.', 'Flávio Bolsonaro' => 'Carreira docente.'],
        ]);

        $this->getJson('/api/presidential-proposal-comparison/themes')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'educacao')
            ->assertJsonPath('data.0.proposals_count', 2);

        $this->getJson('/api/presidential-proposal-comparison/themes/educacao')
            ->assertOk()
            ->assertJsonPath('data.theme.name', 'Educação')
            ->assertJsonCount(2, 'data.candidates')
            ->assertJsonPath('data.comparison.summary', 'Ambos tratam de educação, com focos distintos.')
            ->assertJsonPath('data.candidates.0.proposals.0.source_reference', 'p. 12');
    }

    public function test_it_lists_every_proposal_and_keeps_configured_candidates_without_proposals(): void
    {
        $lula = $this->candidate('Lula', 280002542548, 'PT');
        $flavio = $this->candidate('Flávio Bolsonaro', 280002551544, 'PL');
        $this->candidate('Outro', 999, 'XX'); // sem fonte configurada e sem propostas: fica de fora
        $theme = GovernmentPlanTheme::create(['slug' => 'saude', 'name' => 'Saúde']);

        GovernmentPlanProposal::create([
            'candidate_id' => $lula->id,
            'government_plan_theme_id' => $theme->id,
            'title' => 'Ampliar o SUS',
            'description' => 'Propõe ampliar a rede.',
            'source_excerpt' => 'ampliar a rede',
            'source_url' => 'https://example.test/lula',
            'fingerprint' => str_repeat('c', 64),
        ]);

        $this->getJson('/api/presidential-proposal-comparison/proposals')
            ->assertOk()
            ->assertJsonCount(2, 'data.candidates')
            ->assertJsonPath('data.candidates.0.name', 'Flávio Bolsonaro')
            ->assertJsonPath('data.candidates.1.name', 'Lula')
            ->assertJsonPath('data.themes.0.slug', 'saude')
            ->assertJsonCount(1, 'data.proposals')
            ->assertJsonPath('data.proposals.0.candidate_id', $lula->id)
            ->assertJsonPath('data.proposals.0.theme_slug', 'saude')
            ->assertJsonPath('data.proposals.0.source_excerpt', 'ampliar a rede');

        $this->assertNotNull($flavio->id);
    }

    private function candidate(string $name, int $externalId, string $party): Candidate
    {
        return Candidate::create([
            'external_id' => $externalId,
            'state' => 'BR',
            'office_name' => 'PRESIDENTE',
            'civil_name' => $name,
            'ballot_name' => $name,
            'party_acronym' => $party,
            'election_year' => 2026,
        ]);
    }
}
