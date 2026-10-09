<?php

namespace Tests\Feature\Tse;

use App\Enums\CandidateOffice;
use App\Models\Candidate;
use App\Services\Tse\CandidateSyncService;
use Illuminate\Support\Facades\Process;

/**
 * Cadastro dos vices (vice-governador/vice-presidente) como registros próprios.
 *
 * Cenário fiel ao TSE: a listagem do cargo NÃO traz os vices — eles aparecem
 * só no campo `vices` do detalhe do titular (`sq_CANDIDATO`) e precisam de uma
 * consulta individual ao endpoint de detalhes. O vice vira linha própria em
 * `candidates`, ligada ao titular por `running_mate_of_id` (id INTERNO).
 *
 * Cargo do vice pela tabela oficial do TSE: 4 = VICE-GOVERNADOR (titular = 3);
 * `office_name` guarda o que a API devolve, normalizado ('VICE-GOVERNADOR').
 */
class CandidateViceRegistrationTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    private const MAIN_ID = 60002543969;

    private const VICE_ID = 60002543970;

    public function test_it_registers_the_vice_as_its_own_candidate_record(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()], listedIds: [self::MAIN_ID]);

        $summary = $this->sync();

        $this->assertSame(2, $summary['persisted']);
        $this->assertSame(2, Candidate::count());

        $vice = $this->candidate(self::VICE_ID);

        // Registro próprio, com os dados completos do endpoint de detalhes.
        $this->assertSame('CEZAR ALVES', $vice->ballot_name);
        $this->assertSame('CEZAR ALVES DE SOUZA', $vice->civil_name);
        $this->assertSame('PT', $vice->party_acronym);
        $this->assertSame(self::VICE_ID, (int) $vice->raw_data['id']);

        // Cargo do vice: código e descrição conforme o que o TSE devolve
        // (tabela oficial de cargos: 4 = VICE-GOVERNADOR).
        $this->assertSame(4, $vice->office_code);
        $this->assertSame('VICE-GOVERNADOR', $vice->office_name);
    }

    public function test_it_fills_running_mate_of_id_with_the_internal_id_of_the_main_candidate(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()], listedIds: [self::MAIN_ID]);

        $summary = $this->sync();

        $main = $this->candidate(self::MAIN_ID);
        $vice = $this->candidate(self::VICE_ID);

        $this->assertNull($main->running_mate_of_id);
        $this->assertSame($main->id, $vice->running_mate_of_id);

        // Nunca o SQ_CANDIDATO: a coluna guarda o id interno de `candidates`.
        $this->assertNotSame(self::VICE_ID, $vice->running_mate_of_id);
        $this->assertSame($main->id, $vice->mainCandidate->id);
        $this->assertSame([$vice->id], $main->runningMates()->pluck('id')->all());
        $this->assertSame(1, $summary['running_mates']);
    }

    public function test_it_queries_the_vice_detail_by_its_external_tse_id(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()], listedIds: [self::MAIN_ID]);

        $this->sync();

        // 1º processo: lote da listagem (sem o vice); 2º: busca individual do
        // detalhe do vice pelo SQ_CANDIDATO — o mesmo endpoint dos demais.
        Process::assertRan(fn ($process) => str_contains(
            (string) $process->input,
            '/candidatura/buscar/2026/CE/20322002026/candidato/'.self::VICE_ID,
        ));
        Process::assertRanTimes(fn () => true, 2);
    }

    public function test_it_updates_an_existing_vice_without_duplicating(): void
    {
        // Vice já existe (cadastro anterior), com dados desatualizados e sem
        // vínculo: o sync precisa corrigir tudo numa única gravação.
        Candidate::create([
            'external_id' => self::VICE_ID,
            'election_year' => 2026,
            'state' => 'CE',
            'office_code' => 4,
            'office_name' => 'VICE-GOVERNADOR',
            'civil_name' => 'NOME ANTIGO',
            'ballot_number' => '13',
            'ballot_name' => 'APELIDO ANTIGO',
            'party_acronym' => 'PT',
            'running_mate_of_id' => null,
        ]);

        $this->fakeCandidates([$this->mainWithVice(), $this->vice()], listedIds: [self::MAIN_ID]);

        $summary = $this->sync();

        $this->assertSame(2, $summary['persisted']);
        $this->assertSame(2, Candidate::count());
        $this->assertSame(1, Candidate::where('external_id', self::VICE_ID)->where('election_year', 2026)->count());

        $vice = $this->candidate(self::VICE_ID);

        $this->assertSame('CEZAR ALVES', $vice->ballot_name);
        $this->assertSame('CEZAR ALVES DE SOUZA', $vice->civil_name);
        $this->assertSame($this->candidate(self::MAIN_ID)->id, $vice->running_mate_of_id);
    }

    public function test_re_running_the_sync_does_not_duplicate_titles_or_vices(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()], listedIds: [self::MAIN_ID]);

        $first = $this->sync();
        $second = $this->sync();

        $this->assertSame(2, $first['persisted']);
        $this->assertSame(2, $second['persisted']);
        $this->assertSame(2, Candidate::count());
        $this->assertSame(1, Candidate::where('external_id', self::MAIN_ID)->count());
        $this->assertSame(1, Candidate::where('external_id', self::VICE_ID)->count());
    }

    public function test_the_running_mate_link_is_preserved_on_later_syncs(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()], listedIds: [self::MAIN_ID]);
        $this->sync();

        // "A API muda" entre execuções: novo payload do vice na 2ª sincronização.
        $this->fakeDetails[(string) self::VICE_ID] = $this->vice(['nomeUrna' => 'CEZAR ALVES 2030']);

        $second = $this->sync();

        $vice = $this->candidate(self::VICE_ID);

        // Vínculo válido não é zerado pela atualização, e os dados novos entram.
        $this->assertSame($this->candidate(self::MAIN_ID)->id, $vice->running_mate_of_id);
        $this->assertSame('CEZAR ALVES 2030', $vice->ballot_name);
        $this->assertSame(1, $second['running_mates']);
        $this->assertSame(2, Candidate::count());
    }

    public function test_a_main_candidate_without_vices_is_left_alone(): void
    {
        $this->fakeCandidateApi();

        $summary = $this->sync();

        $this->assertSame(1, $summary['persisted']);
        $this->assertSame(0, $summary['running_mates']);
        $this->assertSame([], $summary['failed']);
        $this->assertSame(1, Candidate::count());
        $this->assertNull(Candidate::firstOrFail()->running_mate_of_id);
    }

    public function test_a_failure_fetching_the_vice_detail_does_not_abort_the_sync(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()], listedIds: [self::MAIN_ID]);
        $this->failDetails([self::VICE_ID]);

        $summary = $this->sync();

        // O titular é persistido normalmente; a falha do vice entra em `failed`
        // e nenhum registro parcial do vice fica para trás.
        $this->assertSame(1, $summary['persisted']);
        $this->assertSame(0, $summary['running_mates']);
        $this->assertCount(1, $summary['failed']);
        $this->assertStringContainsString((string) self::VICE_ID, $summary['failed'][0]);
        $this->assertSame(1, Candidate::count());
        $this->assertSame(self::MAIN_ID, (int) Candidate::firstOrFail()->external_id);
    }

    public function test_file_download_failures_do_not_block_the_vice_registration(): void
    {
        $this->fakeCandidates(
            [$this->mainWithVice(), $this->vice([
                'arquivos' => [
                    ['idArquivo' => 992, 'codTipo' => 5, 'nomeArquivo' => 'Plano'],
                ],
            ])],
            fileStatus: 404,
            listedIds: [self::MAIN_ID],
        );

        $summary = $this->sync(withFiles: true);

        $this->assertSame([], $summary['failed']);
        $this->assertSame(2, Candidate::count());
        $this->assertSame(0, $summary['photos']);
        $this->assertSame(0, $summary['documents']);

        $vice = $this->candidate(self::VICE_ID);

        $this->assertSame($this->candidate(self::MAIN_ID)->id, $vice->running_mate_of_id);
        $this->assertNull($vice->photo_path);
        $this->assertNull($vice->proposal_document_path);
    }

    private function mainWithVice(array $overrides = []): array
    {
        return $this->candidateDetail(array_replace([
            'id' => self::MAIN_ID,
            'nomeUrna' => 'ELMANO DE FREITAS',
            'numero' => 13,
            'cargo' => ['codigo' => 3, 'sigla' => 'GOV', 'nome' => 'Governador', 'codSuperior' => 3, 'titular' => true],
            'vices' => [
                ['sq_CANDIDATO' => self::VICE_ID, 'nomeUrna' => 'CEZAR ALVES', 'nrPartido' => 13],
            ],
        ], $overrides));
    }

    private function vice(array $overrides = []): array
    {
        return $this->candidateDetail(array_replace([
            'id' => self::VICE_ID,
            'nomeUrna' => 'CEZAR ALVES',
            'nomeCompleto' => 'CEZAR ALVES DE SOUZA',
            'numero' => 13,
            'cargo' => ['codigo' => 4, 'sigla' => 'VICE-GOV', 'nome' => 'Vice-Governador', 'codSuperior' => 3, 'titular' => false],
            'vices' => null,
        ], $overrides));
    }

    private function candidate(int $externalId): Candidate
    {
        return Candidate::where('external_id', $externalId)
            ->where('election_year', 2026)
            ->firstOrFail();
    }

    private function sync(bool $withFiles = false): array
    {
        return app(CandidateSyncService::class)->syncOffice(
            year: 2026,
            uf: 'CE',
            electionId: $this->electionId(),
            office: CandidateOffice::Governor,
            disk: 'supabase',
            withFiles: $withFiles,
        );
    }
}
