<?php

namespace Tests\Feature\GovernmentPlans;

use App\Models\Candidate;
use App\Models\GovernmentPlanComparison;
use App\Models\GovernmentPlanProposal;
use App\Models\GovernmentPlanTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresidentialProposalComparisonEndpointsTest extends TestCase
{
    use RefreshDatabase;

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

    private function candidate(string $name, int $externalId, string $party): Candidate
    {
        return Candidate::create([
            'external_id' => $externalId,
            'round' => 1,
            'state' => 'BR',
            'office_name' => 'PRESIDENTE',
            'civil_name' => $name,
            'ballot_name' => $name,
            'party_acronym' => $party,
            'election_year' => 2026,
        ]);
    }
}
