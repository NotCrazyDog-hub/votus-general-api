<?php

namespace Tests\Feature\Tse;

use App\Models\Candidate;

class CandidateApiTest extends CandidateSyncTestCase
{
    public function test_the_listing_returns_the_candidates_and_the_filters_block(): void
    {
        $this->seedCandidate();

        $response = $this->getJson('/api/governor-candidates');

        $response->assertOk()
            ->assertJsonPath('data.0.id', 60002543969)
            ->assertJsonPath('data.0.office', 'GOVERNADOR')
            ->assertJsonPath('data.0.party.acronym', 'PT')
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'ballot_number',
                    'state',
                    'office',
                    'civil_name',
                    'ballot_name',
                    'party' => ['acronym', 'name'],
                    'education_level',
                    'occupation',
                    'race_color',
                    'photo_url',
                    'proposal_document_url',
                    'election_year',
                    'judgment_status',
                ]],
                'filters' => ['parties', 'with_proposal_document', 'with_higher_education', 'with_full_ticket', 'previously_elected'],
                'links',
                'meta',
            ]);

        // Partidos vêm do cache de disco — precisa refletir este teste.
        $this->assertSame(['PT'], $response->json('filters.parties'));
    }

    public function test_the_listing_never_exposes_the_columns_removed_by_the_migration(): void
    {
        $this->seedCandidate();

        $item = $this->getJson('/api/governor-candidates')->json('data.0');

        // `round` saiu de `candidates` (havia uma linha por turno) e
        // `judgment_status_code` foi substituído pelo texto em
        // `judgment_status`. Coluna removida não pode reaparecer na resposta.
        $this->assertArrayNotHasKey('round', $item);
        $this->assertArrayNotHasKey('judgment_status_code', $item);
        $this->assertArrayHasKey('judgment_status', $item);
        $this->assertSame('DEFERIDO', $item['judgment_status']);
    }

    public function test_the_listing_excludes_running_mates(): void
    {
        $main = $this->seedCandidate();
        $this->seedCandidate([
            'external_id' => 60002543970,
            'ballot_name' => 'CEZAR ALVES',
            'civil_name' => 'CEZAR ALVES DE SOUZA',
            'running_mate_of_id' => $main->id,
        ]);

        $response = $this->getJson('/api/governor-candidates');

        // Vices dividem a MESMA tabela e a MESMA listagem — por isso a
        // query usa mainCandidates().
        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(60002543969, $response->json('data.0.id'));
    }

    public function test_the_listing_excludes_candidates_that_were_not_approved(): void
    {
        $this->seedCandidate();
        $this->seedCandidate([
            'external_id' => 60002543971,
            'ballot_name' => 'CANDIDATO INDEFERIDO',
            'judgment_status' => 'INDEFERIDO',
        ]);

        $response = $this->getJson('/api/governor-candidates');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(60002543969, $response->json('data.0.id'));
    }

    public function test_the_listing_only_returns_the_requested_office(): void
    {
        $this->seedCandidate();
        $this->seedCandidate([
            'external_id' => 60002543972,
            'ballot_name' => 'SENADOR X',
            'office_name' => 'SENADOR',
        ]);

        $response = $this->getJson('/api/governor-candidates');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('GOVERNADOR', $response->json('data.0.office'));
    }

    public function test_the_listing_filters_by_state_party_and_search(): void
    {
        $this->seedCandidate();
        $this->seedCandidate([
            'external_id' => 60002543973,
            'state' => 'PB',
            'ballot_name' => 'JOAO PESSOA',
            'civil_name' => 'JOAO PESSOA FILHO',
            'party_acronym' => 'PSB',
            'party_name' => 'PARTIDO SOCIALISTA BRASILEIRO',
        ]);

        $this->getJson('/api/governor-candidates?state=CE')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.state', 'CE');

        $this->getJson('/api/governor-candidates?state=PB')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.party.acronym', 'PSB');

        $this->getJson('/api/governor-candidates?party=PSB')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.state', 'PB');

        // `ilike` é Postgres; no MySQL dos testes vira um comparador inválido
        // (silencioso) — verificado em TempDriverProbeTest. O importante aqui
        // é o filtro chegar na query sem derrubar a rota.
        $this->getJson('/api/governor-candidates?search=ELMANO')
            ->assertOk()
            ->assertJsonPath('data.0.id', 60002543969);

        $this->getJson('/api/governor-candidates?state=RR')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    private function seedCandidate(array $overrides = []): Candidate
    {
        return Candidate::create(array_replace([
            'external_id' => 60002543969,
            'election_year' => 2026,
            'state' => 'CE',
            'office_code' => 3,
            'office_name' => 'GOVERNADOR',
            'coverage_scope' => 'Estadual',
            'civil_name' => 'ELMANO DE FREITAS DA COSTA',
            'ballot_name' => 'ELMANO DE FREITAS',
            'ballot_number' => '13',
            'party_acronym' => 'PT',
            'party_name' => 'PARTIDO DOS TRABALHADORES',
            'education_level' => 'Superior Completo',
            'occupation' => 'Economista',
            'race_color' => 'Parda',
            'judgment_status' => 'DEFERIDO',
            'running_mate_of_id' => null,
        ], $overrides));
    }
}
