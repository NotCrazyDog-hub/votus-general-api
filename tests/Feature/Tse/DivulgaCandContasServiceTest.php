<?php

namespace Tests\Feature\Tse;

use App\Services\Tse\DivulgaCandContasService;
use App\Services\Tse\TseGatewayException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyTimeoutException;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Cobertura direta do serviço contra a ponte Node/Playwright.
 *
 * Os demais testes exercitam o sync de ponta a ponta; aqui o foco é o que só
 * o serviço enxerga: como cada resposta do Chromium vira dado persistido —
 * ou exceção estruturada. Nenhum teste toca TSE/Chromium de verdade: tudo
 * passa por `Process::fake()`.
 */
class DivulgaCandContasServiceTest extends CandidateSyncTestCase
{
    use FakeTseApi;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem isso, o teste do 500 esperaria ~2s de backoff entre retries.
        config()->set('services.tse.divulgacandcontas.retry_delay', 1);
    }

    public function test_it_surfaces_a_403_without_retrying(): void
    {
        $this->fakeTseFailure('TSE_ACCESS_DENIED', 403, 'Akamai bloqueou');

        try {
            $this->service()->listCandidates(2026, 'CE', '20322002026', 7);
            $this->fail('listCandidates deveria lançar TseGatewayException no 403.');
        } catch (TseGatewayException $exception) {
            $this->assertSame(403, $exception->status());
            $this->assertStringContainsString('/candidatura/listar/2026/CE/20322002026/7/candidatos', $exception->getMessage());
        }

        // 403 é definitivo: rodar o Chromium de novo não mudaria nada.
        $this->assertProcessRuns(1);
    }

    public function test_it_retries_a_500_and_then_throws(): void
    {
        config()->set('services.tse.divulgacandcontas.retries', 3);
        $this->fakeTseFailure('TSE_HTTP_500', 500, 'erro interno');

        try {
            $this->service()->listCandidates(2026, 'CE', '20322002026', 7);
            $this->fail('listCandidates deveria lançar TseGatewayException no 500.');
        } catch (TseGatewayException $exception) {
            $this->assertSame(500, $exception->status());
        }

        $this->assertProcessRuns(3);
    }

    public function test_it_rejects_a_non_json_stdout(): void
    {
        // Stdout que não é JSON: o gateway rejeita antes de olhar `success`.
        Process::fake(['*' => Process::result(output: 'não é json {{{')]);

        $this->expectException(TseGatewayException::class);
        $this->expectExceptionMessage('não é JSON válido');

        $this->service()->listCandidates(2026, 'CE', '20322002026', 7);
    }

    public function test_it_rejects_a_process_that_died_without_stdout(): void
    {
        // Processo morreu antes de responder (stdout vazio): exit code e
        // stderr entram na mensagem para diagnóstico.
        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'OOM', exitCode: 1),
        ]);

        $this->expectException(TseGatewayException::class);
        $this->expectExceptionMessage('exit 1');

        $this->service()->listCandidates(2026, 'CE', '20322002026', 7);
    }

    public function test_it_maps_a_timeout_to_a_retryable_gateway_error(): void
    {
        config()->set('services.tse.divulgacandcontas.retries', 2);

        $attempts = 0;
        $this->fakeTimeout($attempts);

        try {
            $this->service()->listCandidates(2026, 'CE', '20322002026', 7);
            $this->fail('listCandidates deveria lançar TseGatewayException no timeout.');
        } catch (TseGatewayException $exception) {
            $this->assertSame('NODE_TIMEOUT', $exception->errorCode());
            $this->assertNull($exception->status());
        }

        // Timeout é recuperável: tenta de novo antes de desistir. O fake que
        // lança exceção não chega a ser gravado em `recorded`, então a
        // contagem é feita no próprio closure.
        $this->assertSame(2, $attempts);
    }

    public function test_it_persists_the_detail_returned_by_the_browser_batch(): void
    {
        $this->fakeCandidateApi();

        $candidates = $this->service()->listCandidates(2026, 'CE', '20322002026', 7);

        $this->assertCount(1, $candidates);
        $this->assertSame(60002543969, $candidates[0]['id']);

        // Uso isolado do serviço (fora do sync): sem lote do mesmo cargo em
        // memória, o detalhe é buscado sozinho pelo navegador — total de 2
        // processos Node (lista + detalhe individual).
        $detail = $this->service()->getCandidate(2026, 'CE', '20322002026', 60002543969);

        $this->assertSame('ELMANO DE FREITAS', $detail['nomeUrna']);

        $this->assertProcessRuns(2);
    }

    private function service(): DivulgaCandContasService
    {
        return app(DivulgaCandContasService::class);
    }

    private function assertProcessRuns(int $count): void
    {
        Process::assertRanTimes(fn () => true, $count);
    }

    /**
     * Simula o Node estourando o timeout: o fake devolve o mesmo Throwable
     * que o `PendingProcess::run()` lançaria num timeout real.
     */
    private function fakeTimeout(int &$attempts): void
    {
        $symfony = new SymfonyProcess(['node']);

        Process::fake([
            '*' => function () use (&$attempts, $symfony) {
                $attempts++;

                return new ProcessTimedOutException(
                    new SymfonyTimeoutException($symfony, SymfonyTimeoutException::TYPE_GENERAL),
                    Process::result(),
                );
            },
        ]);
    }
}
