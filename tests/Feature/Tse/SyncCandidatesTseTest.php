<?php

namespace Tests\Feature\Tse;

use App\Enums\CandidateOffice;
use App\Models\Candidate;
use App\Services\Tse\CandidateSyncService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

class SyncCandidatesTseTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    public function test_it_consults_the_api_and_persists_the_candidate(): void
    {
        $this->fakeCandidateApi();

        $summary = $this->sync();

        $this->assertSame(1, $summary['persisted']);
        $this->assertSame([], $summary['failed']);

        $candidate = Candidate::firstOrFail();

        // external_id é o `id` da API (SQ_CANDIDATO).
        $this->assertSame(60002543969, (int) $candidate->external_id);
        $this->assertSame('13', $candidate->ballot_number);
        $this->assertSame('CE', $candidate->state);
        $this->assertSame('ELMANO DE FREITAS', $candidate->ballot_name);
        $this->assertSame('ELMANO DE FREITAS DA COSTA', $candidate->civil_name);
        $this->assertSame('PT', $candidate->party_acronym);
        $this->assertSame('PARTIDO DOS TRABALHADORES', $candidate->party_name);
        $this->assertSame('Superior Completo', $candidate->education_level);
        $this->assertSame('Economista', $candidate->occupation);
        $this->assertSame('Parda', $candidate->race_color);
        $this->assertSame(3, $candidate->office_code);
        $this->assertSame(2026, $candidate->election_year);

        // Listagem + detalhe saem de UM processo Node só: o input carrega a
        // listUrl montada (ano/UF/electionId/cargo) e o template do detalhe.
        Process::assertRan(fn ($process) => str_contains((string) $process->input, '/candidatura/listar/2026/CE/20322002026/7/candidatos')
            && str_contains((string) $process->input, '/candidatura/buscar/2026/CE/20322002026/candidato/{id}'));
    }

    public function test_it_does_not_duplicate_the_candidate_on_a_second_sync(): void
    {
        $this->fakeCandidateApi();

        $this->sync();
        $this->sync();
        $this->sync();

        $this->assertSame(1, Candidate::count());
    }

    public function test_it_updates_changed_data_instead_of_inserting_again(): void
    {
        $this->fakeCandidateApi();
        $this->sync();

        // A API devolve dados diferentes agora (o stub é reescrito, não
        // acumulado).
        $this->setDetail([
            'descricaoSituacao' => 'Indeferido',
            'ocupacao' => 'Advogado',
        ]);
        $this->sync();

        $this->assertSame(1, Candidate::count());

        $candidate = Candidate::firstOrFail();

        $this->assertSame('INDEFERIDO', $candidate->judgment_status);
        $this->assertSame('Advogado', $candidate->occupation);
    }

    public function test_it_fills_judgment_status_normalized_to_uppercase(): void
    {
        $this->fakeCandidateApi($this->candidateDetail(['descricaoSituacao' => 'Deferido']));
        $this->sync();

        // O projeto compara com "DEFERIDO" nos scopes approved()/rejected().
        $this->assertSame('DEFERIDO', Candidate::firstOrFail()->judgment_status);
    }

    public function test_it_does_not_use_judgment_status_code_or_round_in_candidates(): void
    {
        $this->fakeCandidateApi();
        $this->sync();

        // As duas colunas foram removidas por migrations novas; o código não
        // pode voltar a gravá-las.
        $this->assertFalse(Schema::hasColumn('candidates', 'judgment_status_code'));
        $this->assertFalse(Schema::hasColumn('candidates', 'round'));

        $this->assertNotContains('judgment_status_code', (new Candidate)->getFillable());
        $this->assertNotContains('round', (new Candidate)->getFillable());
        $this->assertArrayNotHasKey('round', (new Candidate)->getCasts());

        // codigoSituacaoCandidato existe no JSON, mas vira raw_data, nunca
        // coluna de código.
        $candidate = Candidate::firstOrFail();
        $this->assertSame(2, $candidate->raw_data['codigoSituacaoCandidato']);
    }

    public function test_it_saves_the_relevant_api_json_in_raw_data(): void
    {
        $this->fakeCandidateApi();
        $this->sync();

        $raw = Candidate::firstOrFail()->raw_data;

        $this->assertIsArray($raw);

        // Campos sem coluna própria ficam preservados em raw_data.
        $this->assertSame('Brasileira', $raw['nacionalidade']);
        $this->assertSame('2026-08-01T10:00:00', $raw['dataUltimaAtualizacao']);
        $this->assertSame('Masculino', $raw['descricaoSexo']);
        $this->assertSame('Fortaleza', $raw['nomeMunicipioNascimento']);
    }

    public function test_it_maps_the_cargo_to_the_office_name_used_by_the_public_api(): void
    {
        // A API devolve "Governador"; as listagens filtram por
        // CandidateOffice::toTseDescription() ("GOVERNADOR").
        $this->fakeCandidateApi($this->candidateDetail([
            'cargo' => ['codigo' => 7, 'nome' => 'Deputado Estadual'],
        ]));

        $sync = app(CandidateSyncService::class);
        $candidate = $sync->persistCandidate($this->candidateDetail([
            'cargo' => ['codigo' => 7, 'nome' => 'Deputado Estadual'],
        ]), 2026, CandidateOffice::StateDeputy);

        $this->assertSame('DEPUTADO ESTADUAL', $candidate->office_name);
        $this->assertSame('DEPUTADO ESTADUAL', CandidateOffice::StateDeputy->toTseDescription());
    }

    public function test_the_main_candidate_keeps_running_mate_of_id_null(): void
    {
        $this->fakeCandidateApi();
        $this->sync();

        $this->assertNull(Candidate::firstOrFail()->running_mate_of_id);
        $this->assertTrue(Candidate::mainCandidates()->count() === 1);
    }

    private function sync(): array
    {
        return app(CandidateSyncService::class)->syncOffice(
            year: 2026,
            uf: 'CE',
            electionId: $this->electionId(),
            office: CandidateOffice::StateDeputy,
            disk: 'supabase',
            withFiles: false,
        );
    }
}
