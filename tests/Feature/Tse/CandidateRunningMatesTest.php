<?php

namespace Tests\Feature\Tse;

use App\Enums\CandidateOffice;
use App\Models\Candidate;
use App\Services\Tse\CandidateSyncService;
use Illuminate\Database\Eloquent\Builder;

class CandidateRunningMatesTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    private const MAIN_ID = 60002543969;

    private const VICE_ID = 60002543970;

    public function test_it_links_the_vice_to_the_main_candidate_after_the_sync(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()]);

        $summary = $this->sync();

        $this->assertSame(2, $summary['persisted']);
        $this->assertSame(1, $summary['running_mates']);

        $main = $this->candidate(self::MAIN_ID);
        $vice = $this->candidate(self::VICE_ID);

        $this->assertNull($main->running_mate_of_id);
        $this->assertSame($main->id, $vice->running_mate_of_id);
        $this->assertSame([$vice->id], $main->runningMates()->pluck('id')->all());
        // `mainCandidate()` devolve a relação; o model vem do lazy-load.
        $this->assertSame($main->id, $vice->mainCandidate->id);
    }

    public function test_the_link_survives_a_second_sync(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()]);

        $first = $this->sync();
        $this->assertSame(1, $first['running_mates']);

        // persistCandidate grava `running_mate_of_id => null` em toda
        // atualização — o segundo sync zera o vice de propósito e o passo
        // final religa. Sem isso, uma reexecução deixaria órfãos.
        $second = $this->sync();

        $this->assertSame(1, $second['running_mates']);
        $this->assertSame(2, Candidate::count());
        $this->assertSame($this->candidate(self::MAIN_ID)->id, $this->candidate(self::VICE_ID)->running_mate_of_id);
    }

    public function test_a_candidate_without_vices_stays_a_main_candidate(): void
    {
        $this->fakeCandidateApi();

        $summary = $this->sync();

        $this->assertSame(0, $summary['running_mates']);
        $this->assertNull(Candidate::firstOrFail()->running_mate_of_id);
    }

    public function test_a_running_mate_from_another_election_year_is_not_linked(): void
    {
        // O SQ_CANDIDATO apontado pelo titular existe, mas pertence à eleição
        // de 2022. Ligar mudaria o histórico corrente por um vínculo errado.
        Candidate::create([
            'external_id' => 60001111111,
            'election_year' => 2022,
            'state' => 'CE',
            'office_name' => 'Governador',
            'civil_name' => 'ELMANO DE FREITAS DA COSTA',
            'ballot_number' => '13',
            'ballot_name' => 'ELMANO DE FREITAS',
            'party_acronym' => 'PT',
            'running_mate_of_id' => null,
        ]);

        $this->fakeCandidates([$this->mainWithVice(['vices' => [
            ['sq_CANDIDATO' => 60001111111, 'nomeUrna' => 'ANTIGO'],
        ]])]);

        $summary = $this->sync();

        $this->assertSame(0, $summary['running_mates']);
        $this->assertNull(Candidate::where('external_id', 60001111111)->firstOrFail()->running_mate_of_id);
    }

    public function test_a_running_mate_that_was_not_synced_is_skipped(): void
    {
        // O titular aponta um vice que NÃO está na listagem — o vínculo fica
        // pendente até o vice ser sincronizado.
        $this->fakeCandidates([$this->mainWithVice()]);

        $summary = $this->sync();

        $this->assertSame(1, $summary['persisted']);
        $this->assertSame(0, $summary['running_mates']);
        $this->assertSame(0, $this->runningMates()->count());
    }

    public function test_the_link_can_be_rebuilt_later_by_the_command(): void
    {
        // O valor do comando é religar SEM voltar à API do TSE — é o caso de
        // dados legados ou de um sync que foi interrompido no meio.
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()]);
        $this->sync();

        $this->runningMates()->update(['running_mate_of_id' => null]);
        $this->assertSame(0, $this->runningMates()->count());

        $this->artisan('candidates:link-running-mates', ['--year' => 2026, '--uf' => 'CE'])
            ->expectsOutputToContain('1 vínculo(s) criado(s).')
            ->assertExitCode(0);

        $this->assertSame(1, $this->runningMates()->count());
        $this->assertSame(
            $this->candidate(self::MAIN_ID)->id,
            $this->candidate(self::VICE_ID)->running_mate_of_id,
        );
    }

    public function test_the_link_command_is_idempotent(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()]);
        $this->sync();

        $this->artisan('candidates:link-running-mates', ['--year' => 2026])
            ->expectsOutputToContain('0 vínculo(s) criado(s).')
            ->assertExitCode(0);

        $this->assertSame(1, $this->runningMates()->count());
    }

    public function test_the_link_command_respects_the_uf_filter(): void
    {
        $this->fakeCandidates([$this->mainWithVice(), $this->vice()]);
        $this->sync();

        // Limpa o vínculo para provar que só o filtro de UF impede o religamento.
        $this->runningMates()->update(['running_mate_of_id' => null]);

        $this->artisan('candidates:link-running-mates', ['--year' => 2026, '--uf' => 'RS'])
            ->assertExitCode(0);

        $this->assertSame(0, $this->runningMates()->count());

        $this->artisan('candidates:link-running-mates', ['--year' => 2026, '--uf' => 'CE'])
            ->assertExitCode(0);

        $this->assertSame(1, $this->runningMates()->count());
    }

    private function vice(array $overrides = []): array
    {
        return $this->candidateDetail(array_replace([
            'id' => self::VICE_ID,
            'nomeUrna' => 'CEZAR ALVES',
            'numero' => 13,
            'cargo' => ['codigo' => 3, 'sigla' => 'GOV', 'nome' => 'Governador', 'codSuperior' => 3, 'titular' => false],
            'vices' => null,
        ], $overrides));
    }

    private function candidate(int $externalId): Candidate
    {
        return Candidate::where('external_id', $externalId)
            ->where('election_year', 2026)
            ->firstOrFail();
    }

    /**
     * `Candidate::runningMates()` estático não funciona: a relação homônima
     * (linha 71 do model) é um método público não estático, e o PHP NÃO
     * aciona `__callStatic` nesse caso — ele lança "cannot be called
     * statically", sombreando `scopeRunningMates()`. A query explícita é o
     * caminho certo.
     *
     * @return Builder<Candidate>
     */
    private function runningMates()
    {
        return Candidate::query()->whereNotNull('running_mate_of_id');
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
