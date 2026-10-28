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

    public function test_the_list_can_be_filtered_by_election_result_across_the_whole_list(): void
    {
        $elected = $this->candidate('Eleita', 2001);
        $this->history($elected, 1, 'Eleito por QP');

        $out = $this->candidate('Não eleito', 2002);
        $this->history($out, 1, 'Não eleito');

        $alternate = $this->candidate('Suplente', 2003);
        $this->history($alternate, 1, 'Suplente');

        $runoff = $this->candidate('No segundo turno', 2004);
        $this->history($runoff, 1, '2º turno');
        $this->history($runoff, 2, null);

        // 2º turno já decidido: deixa de ser "segundo turno".
        $wonRunoff = $this->candidate('Venceu o 2º turno', 2005);
        $this->history($wonRunoff, 1, '2º turno');
        $this->history($wonRunoff, 2, 'Eleito');

        $awaiting = $this->candidate('Aguardando', 2006);
        $this->history($awaiting, 1, null);

        $this->candidate('Sem registro', 2007);

        // Resultado de OUTRA eleição não vale para a de 2026.
        $otherYear = $this->candidate('Só 2022', 2008);
        CandidacyHistory::create([
            'candidate_id' => $otherYear->id, 'candidacy_external_id' => '2008', 'election_year' => 2022,
            'round' => 1, 'state' => 'BR', 'office_name' => 'PRESIDENTE', 'result_status' => 'Eleito',
        ]);

        $ids = fn (string $status) => collect($this->getJson("/api/president-candidates?election_result={$status}")
            ->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([2001, 2005], $ids('elected'));
        $this->assertSame([2002, 2003], $ids('not_elected'));
        $this->assertSame([2004], $ids('runoff'));
        $this->assertSame([2006], $ids('awaiting'));
        $this->assertSame([2007, 2008], $ids('unverified'));

        // O filtro combina com a busca e as contagens vêm no bloco "filters".
        $this->getJson('/api/president-candidates?election_result=elected&search=Venceu')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('filters.election_results', [
                'elected' => 2, 'not_elected' => 2, 'runoff' => 1, 'awaiting' => 1, 'unverified' => 2,
            ]);

        $this->getJson('/api/president-candidates?election_result=inventado')->assertStatus(422);
    }

    public function test_the_list_shows_confirmed_results_first(): void
    {
        // Nomes em ordem alfabética inversa à da situação, para provar que a
        // situação vem antes do nome.
        $this->candidate('A Sem registro', 3001);
        $awaiting = $this->candidate('B Aguardando', 3002);
        $this->history($awaiting, 1, null);
        $out = $this->candidate('C Não eleito', 3003);
        $this->history($out, 1, 'Não eleito');
        $lostRunoff = $this->candidate('D Perdeu o 2º turno', 3004);
        $this->history($lostRunoff, 1, '2º turno');
        $this->history($lostRunoff, 2, 'Não eleito');
        $runoff = $this->candidate('E No segundo turno', 3005);
        $this->history($runoff, 1, '2º turno');
        $this->history($runoff, 2, null);
        $elected = $this->candidate('F Eleito', 3006);
        $this->history($elected, 1, '2º turno');
        $this->history($elected, 2, 'Eleito');
        $this->candidate('G Sem registro', 3007);

        $ids = collect($this->getJson('/api/president-candidates')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([3006, 3005, 3003, 3004, 3002, 3001, 3007], $ids);
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
