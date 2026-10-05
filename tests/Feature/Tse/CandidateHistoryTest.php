<?php

namespace Tests\Feature\Tse;

use App\Enums\CandidateOffice;
use App\Models\CandidacyHistory;
use App\Models\Candidate;
use App\Services\Tse\CandidateSyncService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

class CandidateHistoryTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    public function test_it_saves_only_elections_before_the_synced_year(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'eleicoesAnteriores' => [
                $this->previousElection(['nrAno' => 2018, 'id' => '60001111111', 'situacaoTotalizacao' => 'Não eleito']),
                $this->previousElection(['nrAno' => 2022, 'id' => '60001633585', 'situacaoTotalizacao' => 'Eleito']),
                // O ano corrente (2026) já está em `candidates`; não pode
                // aparecer duas vezes.
                $this->previousElection(['nrAno' => 2026, 'id' => '60002543969']),
                // E o futuro nem existe — descartado mesmo assim.
                $this->previousElection(['nrAno' => 2030, 'id' => '60009999999']),
            ],
        ]));

        $summary = $this->sync();

        // `histories` conta itens GRAVADOS (2018 e 2022); 2026 e 2030 foram
        // descartados antes de chegar ao contador.
        $this->assertSame(2, $summary['histories']);
        $this->assertSame(2, CandidacyHistory::count());

        $years = CandidacyHistory::orderBy('election_year')->pluck('election_year')->all();
        $this->assertSame([2018, 2022], $years);
    }

    public function test_it_keeps_round_null_because_the_api_does_not_expose_it(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'eleicoesAnteriores' => [$this->previousElection()],
        ]));

        $this->sync();

        $history = CandidacyHistory::firstOrFail();

        // NR_TURNO não existe na API; inventar 1/2 aqui mudaria o resultado do
        // histórico. A coluna fica null até a etapa de resultados preenchê-la.
        $this->assertNull($history->round);
        $this->assertNull($history->getAttributes()['round'] ?? null);
    }

    public function test_it_maps_the_history_fields_from_the_api_payload(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'eleicoesAnteriores' => [$this->previousElection()],
        ]));

        $this->sync();

        $candidate = Candidate::firstOrFail();
        $history = CandidacyHistory::firstOrFail();

        $this->assertSame($candidate->id, $history->candidate_id);
        $this->assertSame(60001633585, (int) $history->candidacy_external_id);
        $this->assertSame(2022, $history->election_year);
        $this->assertSame('CE', $history->state);
        $this->assertSame('Governador', $history->office_name);
        $this->assertSame('13', $history->ballot_number);
        $this->assertSame('PT', $history->party_acronym);
        $this->assertNull($history->party_name);
        $this->assertNull($history->candidacy_status);
        $this->assertSame('Eleito', $history->result_status);
        $this->assertSame('60001633585', $history->raw_data['id']);
    }

    public function test_it_does_not_duplicate_history_on_a_second_sync(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'eleicoesAnteriores' => [$this->previousElection()],
        ]));

        $this->sync();
        $this->sync();
        $this->sync();

        $this->assertSame(1, CandidacyHistory::count());
        $this->assertSame(1, Candidate::count());
    }

    public function test_it_skips_history_items_without_year_or_id(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'eleicoesAnteriores' => [
                ['nomeUrna' => 'SEM ANO', 'id' => '60001633585'],  // sem nrAno
                ['nrAno' => 2022],                                  // sem id
                ['nrAno' => 2022, 'id' => ''],                      // id vazio
                'não é um array',                                   // lixo
                $this->previousElection(['nrAno' => 2020, 'id' => '60001555555']),
            ],
        ]));

        $summary = $this->sync();

        $this->assertSame(1, $summary['histories']);
        $this->assertSame(1, CandidacyHistory::count());
        $this->assertSame(2020, CandidacyHistory::firstOrFail()->election_year);
    }

    public function test_a_non_array_history_block_is_ignored(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'eleicoesAnteriores' => 'não é lista',
        ]));

        $summary = $this->sync();

        $this->assertSame(0, $summary['histories']);
        $this->assertSame(0, CandidacyHistory::count());
        $this->assertSame(1, $summary['persisted']);
    }

    public function test_the_unique_key_ignores_round_so_a_resync_never_duplicates(): void
    {
        $this->fakeCandidateApi($this->candidateDetail([
            'eleicoesAnteriores' => [$this->previousElection()],
        ]));

        $this->sync();

        $this->assertTrue(Schema::hasColumn('candidacy_histories', 'round'));

        // A migration nova tirou `round` do unique e passou a chavear por
        // [candidate_id, candidacy_external_id] — com `round` sempre null, o
        // índice antigo `(candidacy_external_id, round)` não colidia (NULL nunca
        // é igual a NULL), e reexecutar o sync criaria linha repetida.
        //
        // Prova comportamental em vez de `SHOW INDEX`, que é SQL só do MySQL —
        // o repo também roda em SQLite. Se `round` entrasse na chave, duas linhas
        // da MESMA candidatura com `round` diferente coexistiriam; como não
        // entra, o insert é barrado.
        $original = CandidacyHistory::firstOrFail();
        $duplicate = $original->replicate();
        $duplicate->round = $original->round === null ? 2 : $original->round + 1;

        $this->expectException(UniqueConstraintViolationException::class);

        $duplicate->save();
    }

    private function sync(): array
    {
        return app(CandidateSyncService::class)->syncOffice(
            year: 2026,
            uf: 'CE',
            electionId: $this->electionId(),
            office: CandidateOffice::Governor,
            disk: 'supabase',
            withFiles: false,
        );
    }
}
