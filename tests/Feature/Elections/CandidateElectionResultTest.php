<?php

namespace Tests\Feature\Elections;

use App\Domains\Elections\Models\CandidacyHistory;
use App\Domains\Elections\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MigratesTestSchema;
use Tests\TestCase;

class CandidateElectionResultTest extends TestCase
{
    use MigratesTestSchema, RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return $this->testSchemaMigrateFreshUsing();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('supabase');
        // CandidateService guarda contagens em cache de disco (ver CandidateSyncTestCase).
        Cache::store('file')->flush();
    }

    public function test_the_list_and_the_profile_expose_the_classified_election_result(): void
    {
        $runoff = $this->candidate('Candidata A', 1001);
        $this->history($runoff, 1, '2º turno');
        $this->history($runoff, 2, null); // 2º turno ainda sem resultado

        $out = $this->candidate('Candidato B', 1002);
        $this->history($out, 1, 'Não eleito');

        $pending = $this->candidate('Candidato C', 1003);
        $this->history($pending, 1, null);

        $this->candidate('Candidato D', 1004); // sem nenhum registro de resultado

        $list = collect($this->getJson('/api/president-candidates')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame('runoff', $list[1001]['election_result']['status']);
        $this->assertSame('Segundo turno', $list[1001]['election_result']['label']);
        $this->assertSame('2º turno', $list[1001]['election_status']); // campo antigo preservado
        $this->assertSame('not_elected', $list[1002]['election_result']['status']);
        $this->assertSame('awaiting', $list[1003]['election_result']['status']);
        $this->assertNull($list[1003]['election_status']);
        $this->assertSame('unverified', $list[1004]['election_result']['status']);

        $this->getJson('/api/president-candidates/1001')
            ->assertOk()
            ->assertJsonPath('data.election_result.status', 'runoff')
            ->assertJsonPath('data.election_result.official_text', '2º turno')
            ->assertJsonPath('data.election_result.round', 1)
            ->assertJsonPath('data.election_result.source', 'TSE');
    }

    private function candidate(string $name, int $externalId): Candidate
    {
        return Candidate::create([
            'external_id' => $externalId,
            'state' => 'BR',
            'office_name' => 'PRESIDENTE',
            'civil_name' => $name,
            'ballot_name' => $name,
            'party_acronym' => 'XX',
            'election_year' => 2026,
            'judgment_status' => 'DEFERIDO',
        ]);
    }

    private function history(Candidate $candidate, int $round, ?string $result): void
    {
        CandidacyHistory::create([
            'candidate_id' => $candidate->id,
            'candidacy_external_id' => (string) $candidate->external_id,
            'election_year' => 2026,
            'round' => $round,
            'state' => 'BR',
            'office_name' => 'PRESIDENTE',
            'result_status' => $result,
        ]);
    }
}
