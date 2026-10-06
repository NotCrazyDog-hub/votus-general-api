<?php

namespace Tests\Feature\Tse;

use App\Models\Candidate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class SyncCandidatesTseCommandTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem isso, cada teste que força um 5xx esperaria ~2s de backoff.
        config()->set('services.tse.divulgacandcontas.retry_delay', 1);
    }

    public function test_it_syncs_the_office_and_reports_the_summary(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2026,
            '--office' => 'governor',
        ])
            ->expectsOutputToContain('Sincronização concluída: 1 candidato(s)')
            ->assertExitCode(0);

        $candidate = Candidate::firstOrFail();

        $this->assertSame(60002543969, (int) $candidate->external_id);
        $this->assertSame('CE', $candidate->state);
        $this->assertSame('GOVERNADOR', $candidate->office_name);
        $this->assertSame('DEFERIDO', $candidate->judgment_status);
    }

    public function test_it_builds_the_list_url_from_year_uf_election_id_and_cargo(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'ce',
            '--year' => 2026,
            '--office' => 'governor',
        ])->assertExitCode(0);

        // Listagem + detalhes saem num ÚNICO processo Node: o input carrega a
        // listUrl montada (ano/UF/electionId/cargo) e o template do detalhe.
        // A listagem só descobre IDs; a persistência vem SEMPRE do detalhe.
        Process::assertRan(fn ($process) => str_contains((string) $process->input, '/candidatura/listar/2026/CE/20322002026/3/candidatos')
            && str_contains((string) $process->input, '/candidatura/buscar/2026/CE/20322002026/candidato/{id}'));
    }

    public function test_it_maps_the_office_to_its_tse_cargo_code(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2026,
            '--office' => 'state_deputy',
        ])->assertExitCode(0);

        // 7 = Deputado Estadual; o cargo errado devolveria lista vazia.
        Process::assertRan(fn ($process) => str_contains((string) $process->input, '/2026/CE/20322002026/7/candidatos'));
    }

    public function test_it_downloads_files_by_default(): void
    {
        $this->fakeCandidateApi([
            'arquivos' => [
                ['idArquivo' => 991, 'codTipo' => 5, 'nomeArquivo' => 'Plano'],
                ['idArquivo' => 990, 'codTipo' => 3, 'nomeArquivo' => 'Resumo'],
            ],
        ]);

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2026,
            '--office' => 'governor',
        ])->assertExitCode(0);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/arquivo/img/'));
        // codTipo = 5 é o plano de governo; 990 (codTipo 3) não pode ser baixado.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/arquivo/doc/991'));

        $candidate = Candidate::firstOrFail();

        $this->assertNotNull($candidate->photo_path);
        $this->assertNotNull($candidate->proposal_document_path);
        // photo_path é caminho, nunca a URL de origem do TSE.
        $this->assertStringNotContainsString('http', $candidate->photo_path);
    }

    public function test_with_without_files_no_download_is_attempted(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2026,
            '--office' => 'governor',
            '--without-files' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, $this->fileRequests());
        $this->assertNull(Candidate::firstOrFail()->photo_path);
        $this->assertNull(Candidate::firstOrFail()->proposal_document_path);
    }

    public function test_it_rejects_an_unknown_office(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2026,
            '--office' => 'dogcatcher',
        ])
            ->expectsOutputToContain('Cargo inválido')
            ->assertExitCode(1);

        // Rejeitado antes de qualquer chamada de rede.
        $this->assertSame(0, Candidate::count());
        Process::assertNothingRan();
    }

    public function test_it_fails_when_the_year_has_no_known_election_id(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2020,
            '--office' => 'governor',
        ])
            ->expectsOutputToContain('Não há electionId conhecido para 2020')
            ->assertExitCode(1);

        $this->assertSame(0, Candidate::count());
    }

    public function test_an_explicit_election_id_overrides_the_derived_one(): void
    {
        $this->fakeCandidateApi();

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2020,
            '--office' => 'governor',
            '--election-id' => '20322002020',
        ])->assertExitCode(0);

        Process::assertRan(fn ($process) => str_contains((string) $process->input, '/candidatura/listar/2020/CE/20322002020/'));
    }

    public function test_it_fails_when_the_listing_endpoint_is_unreachable(): void
    {
        // O script reportou o 500 do TSE, mas conseguiu responder: o comando
        // recebe TseGatewayException com status e sai com erro.
        $this->fakeTseFailure('TSE_HTTP_500', 500, 'erro interno');

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2026,
            '--office' => 'governor',
        ])
            ->expectsOutputToContain('Falha ao sincronizar candidatos')
            ->assertExitCode(1);

        $this->assertSame(0, Candidate::count());
    }

    public function test_a_failing_candidate_detail_does_not_abort_the_whole_run(): void
    {
        // Listagem devolve dois IDs; o segundo detalhe responde 404.
        $this->fakeCandidates([
            $this->candidateDetail(),
            $this->candidateDetail(['id' => 60002543970, 'nomeUrna' => 'QUEBRADA']),
        ]);
        $this->failDetails([60002543970]);

        $this->artisan('sync:candidates-tse', [
            '--uf' => 'CE',
            '--year' => 2026,
            '--office' => 'governor',
        ])
            ->expectsOutputToContain('1 candidato(s) falharam')
            ->assertExitCode(0);

        $this->assertSame(1, Candidate::count());
        $this->assertSame(60002543969, (int) Candidate::firstOrFail()->external_id);
    }

    /**
     * Conta requisições que tocam /arquivo (foto e documento).
     */
    private function fileRequests(): int
    {
        $count = 0;

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), '/arquivo/')) {
                $count++;
            }
        }

        return $count;
    }
}
