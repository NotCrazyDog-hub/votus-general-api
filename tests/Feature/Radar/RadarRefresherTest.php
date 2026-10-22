<?php

namespace Tests\Feature\Radar;

use App\Domains\Radar\Jobs\RefreshRadarJob;
use App\Domains\Radar\Services\CamaraRadarCollector;
use App\Domains\Radar\Services\RadarRefresher;
use App\Domains\Radar\Services\RadarUfs;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RadarRefresherTest extends RadarTestCase
{
    public function test_a_valid_result_is_published_with_its_status(): void
    {
        $this->fakeCamara(fn () => $this->camaraResult(2025, ['CE' => [10, ['Saúde' => 4]]]));

        $status = app(RadarRefresher::class)->refresh('camara', 2025);

        $this->assertSame('published', $status);
        $this->assertSame(10, $this->store()->result('camara', 2025)['states']['CE']['propositions']);
        $this->assertNotNull($this->store()->status('camara', 2025)['last_success_at']);
    }

    public function test_a_failure_keeps_the_previous_result_and_records_the_error(): void
    {
        $previous = $this->camaraResult(2025, ['CE' => [10, ['Saúde' => 4]]]);
        $this->store()->publish('camara', 2025, $previous);

        $this->fakeCamara(fn () => throw new RuntimeException('Download incompleto após 40 tentativas'));

        $status = app(RadarRefresher::class)->refresh('camara', 2025, force: true);

        $this->assertSame('failed', $status);
        $this->assertSame($previous, $this->store()->result('camara', 2025));
        $this->assertStringContainsString('Download incompleto', $this->store()->status('camara', 2025)['last_error']);
        $this->assertNotNull($this->store()->status('camara', 2025)['last_error_at']);
    }

    public function test_a_much_smaller_camara_result_is_rejected_as_incomplete(): void
    {
        $previous = $this->camaraResult(2025, ['CE' => [300, ['Saúde' => 100]]]);
        $this->store()->publish('camara', 2025, $previous);

        $this->fakeCamara(fn () => $this->camaraResult(2025, ['CE' => [12, ['Saúde' => 3]]], startedAtMs: PHP_INT_MAX));

        $this->assertSame('failed', app(RadarRefresher::class)->refresh('camara', 2025, force: true));
        $this->assertSame(300, $this->store()->result('camara', 2025)['states']['CE']['propositions']);
    }

    public function test_an_empty_camara_result_is_never_published(): void
    {
        $this->fakeCamara(fn () => $this->camaraResult(2025, []));

        $this->assertSame('failed', app(RadarRefresher::class)->refresh('camara', 2025));
        $this->assertNull($this->store()->result('camara', 2025));
    }

    public function test_a_concurrent_refresh_of_the_same_scope_is_blocked(): void
    {
        $this->fakeCamara(fn () => $this->fail('não deveria coletar com o lock tomado'));

        $lock = $this->store()->refreshLock('camara', 2025, 60);
        $this->assertTrue($lock->get());

        try {
            $this->assertSame('skipped:locked', app(RadarRefresher::class)->refresh('camara', 2025));
        } finally {
            $lock->release();
        }
    }

    public function test_an_older_computation_never_replaces_a_newer_published_result(): void
    {
        $newer = $this->camaraResult(2025, ['CE' => [20, ['Saúde' => 4]]], startedAtMs: 2000);
        $older = $this->camaraResult(2025, ['CE' => [10, ['Saúde' => 4]]], startedAtMs: 1000);

        $this->assertTrue($this->store()->publish('camara', 2025, $newer));
        $this->assertFalse($this->store()->publish('camara', 2025, $older));
        $this->assertSame(20, $this->store()->result('camara', 2025)['states']['CE']['propositions']);
    }

    public function test_a_fresh_result_is_not_recomputed(): void
    {
        $this->store()->publish('camara', 2025, $this->camaraResult(2025, ['CE' => [10, ['Saúde' => 4]]]));
        $this->fakeCamara(fn () => $this->fail('não deveria coletar um resultado em dia'));

        $this->assertSame('skipped:fresh', app(RadarRefresher::class)->refresh('camara', 2025));
    }

    public function test_it_only_writes_radar_keys_and_leaves_other_cache_entries_alone(): void
    {
        Cache::store('array')->forever('candidates:list', ['x']);

        $written = [];
        Event::listen(KeyWritten::class, function (KeyWritten $event) use (&$written) {
            $written[] = $event->key;
        });

        $this->fakeCamara(fn () => $this->camaraResult(2025, ['CE' => [10, ['Saúde' => 4]]]));
        app(RadarRefresher::class)->refresh('camara', 2025);

        $this->fakeCamara(fn () => throw new RuntimeException('falhou'));
        app(RadarRefresher::class)->refresh('camara', 2025, force: true);

        $this->assertNotEmpty($written);
        foreach ($written as $key) {
            $this->assertStringStartsWith('radar:v1:', $key);
        }
        $this->assertSame(['x'], Cache::store('array')->get('candidates:list'));
    }

    public function test_the_job_runs_on_the_radar_queue_and_never_throws(): void
    {
        $this->fakeCamara(fn () => throw new RuntimeException('fonte fora do ar'));

        $job = new RefreshRadarJob('camara', 2025);
        $this->assertSame('radar', $job->queue);

        $job->handle(app(RadarRefresher::class)); // não lança

        $this->assertNull($this->store()->result('camara', 2025));
        $this->assertSame('fonte fora do ar', $this->store()->status('camara', 2025)['last_error']);
    }

    public function test_invalid_scopes_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(RadarRefresher::class)->compute('senado', 'ZZ');
    }

    public function test_ibge_indicators_are_read_for_all_states_with_their_period(): void
    {
        Http::fake(['servicodados.ibge.gov.br/*' => fn ($request) => Http::response($this->ibgeResponse(
            str_contains($request->url(), '/9324') ? '2025' : '2022',
        ))]);

        $this->assertSame('published', app(RadarRefresher::class)->refresh('ibge', 'all'));

        $population = $this->store()->result('ibge', 'all')['indicators']['population'];
        $this->assertSame('2025', $population['period']);
        $this->assertSame('Pessoas', $population['unit']);
        $this->assertCount(27, $population['values']);
        $this->assertSame(1023, $population['values']['CE']);
    }

    public function test_ibge_with_a_missing_value_is_not_published(): void
    {
        Http::fake(['servicodados.ibge.gov.br/*' => Http::response($this->ibgeResponse('2025', missing: 'AM'))]);

        $this->assertSame('failed', app(RadarRefresher::class)->refresh('ibge', 'all'));
        $this->assertNull($this->store()->result('ibge', 'all'));
    }

    public function test_senado_counts_each_bill_once_per_state_from_the_legislature_start(): void
    {
        $this->fakeSenado(failingDetails: []);

        $this->assertSame('published', app(RadarRefresher::class)->refresh('senado', 'CE'));

        $result = $this->store()->result('senado', 'CE');
        // 7001 (coautoria dos dois senadores) conta 1 vez; 7003 é de 2022 e fica fora; REQ fica fora
        $this->assertSame(2, $result['legislature']['propositions']);
        $this->assertSame([['name' => 'Saúde', 'count' => 2], ['name' => 'Educação', 'count' => 1]], $result['legislature']['topics']);
        $this->assertSame(1, $result['years'][2023]['propositions']);
        $this->assertSame(1, $result['years'][2025]['propositions']);
        $this->assertCount(2, $result['senators']);
    }

    public function test_senado_with_too_many_failed_details_is_not_published(): void
    {
        config(['radar.senado.sleep_ms' => 0]);
        $this->fakeSenado(failingDetails: ['7002']);

        $this->assertSame('failed', app(RadarRefresher::class)->refresh('senado', 'CE'));
        $this->assertNull($this->store()->result('senado', 'CE'));
    }

    private function fakeCamara(\Closure $collect): void
    {
        $this->mock(CamaraRadarCollector::class, fn ($mock) => $mock->shouldReceive('collect')->andReturnUsing($collect));
    }

    private function ibgeResponse(string $period, ?string $missing = null): array
    {
        $series = [];
        foreach (RadarUfs::ALL as $uf => $info) {
            $series[] = [
                'localidade' => ['id' => (string) $info['ibge_code'], 'nivel' => ['id' => 'N3'], 'nome' => $info['name']],
                'serie' => [$period => $uf === $missing ? '-' : (string) (1000 + $info['ibge_code'])],
            ];
        }

        return [['id' => '9324', 'variavel' => 'Variável', 'unidade' => 'Pessoas', 'resultados' => [['classificacoes' => [], 'series' => $series]]]];
    }

    private function fakeSenado(array $failingDetails): void
    {
        config(['radar.senado.sleep_ms' => 0]);

        $senator = fn ($code, $name) => ['IdentificacaoParlamentar' => ['CodigoParlamentar' => $code, 'NomeParlamentar' => $name, 'UfParlamentar' => 'CE'], 'Mandato' => ['DescricaoParticipacao' => 'Titular']];
        $bill = fn ($id, $ident, $date) => ['id' => $id, 'identificacao' => $ident, 'dataApresentacao' => $date];

        Http::fake(function ($request) use ($senator, $bill, $failingDetails) {
            $url = $request->url();

            if (str_contains($url, '/senador/lista/atual')) {
                return Http::response(['ListaParlamentarEmExercicio' => ['Parlamentares' => ['Parlamentar' => [
                    $senator('1', 'Senador A'),
                    $senator('2', 'Senador B'),
                    ['IdentificacaoParlamentar' => ['CodigoParlamentar' => '3', 'UfParlamentar' => 'SP']],
                ]]]]);
            }

            if (preg_match('#/processo\?.*codigoParlamentarAutor=(\d)#', $url, $m)) {
                return Http::response($m[1] === '1'
                    ? [$bill(7001, 'PL 1/2023', '2023-05-10'), $bill(7003, 'PL 9/2022', '2022-03-01'), $bill(7004, 'REQ 1/2025', '2025-01-01')]
                    : [$bill(7001, 'PL 1/2023', '2023-05-10'), $bill(7002, 'PEC 2/2025', '2025-08-01')]);
            }

            if (preg_match('#/processo/(\d+)$#', $url, $m)) {
                if (in_array($m[1], $failingDetails, true)) {
                    return Http::response('erro', 500);
                }

                return Http::response(['classificacoes' => $m[1] === '7001'
                    ? [['codigo' => 1, 'descricao' => 'Saúde'], ['codigo' => 2, 'descricao' => 'Educação']]
                    : [['codigo' => 1, 'descricao' => 'Saúde']]]);
            }

            return Http::response('inesperado: ' . $url, 404);
        });
    }
}
