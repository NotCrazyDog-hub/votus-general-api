<?php

namespace Tests\Feature\Radar;

use App\Domains\Radar\Services\RadarStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Base dos testes do Radar: cache do Radar em memória (store 'array'),
 * nenhuma chamada HTTP real e nenhum uso de banco.
 */
abstract class RadarTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Trava de segurança: o .env local aponta para o Supabase de
        // produção. Estes testes só rodam no SQLite em memória do phpunit.xml.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        config(['radar.cache_store' => 'array']);
        Http::preventStrayRequests();
    }

    protected function store(): RadarStore
    {
        return app(RadarStore::class);
    }

    /**
     * Resultado anual da Câmara no formato do CamaraRadarCollector.
     *
     * @param  array<string, array{0: int, 1: array<string, int>}>  $states  UF => [proposições, [tema => contagem]]
     */
    protected function camaraResult(int $year, array $states, int $startedAtMs = 1000): array
    {
        $out = [];

        foreach ($states as $uf => [$propositions, $topics]) {
            $list = [];
            foreach ($topics as $name => $count) {
                // codTema estável por nome, como na fonte oficial
                $list[] = ['code' => (string) crc32($name), 'name' => $name, 'count' => $count];
            }

            $out[$uf] = [
                'propositions' => $propositions,
                'deputies' => 5,
                'multi_theme_propositions' => 1,
                'multi_author_same_uf_propositions' => 0,
                'topics' => $list,
            ];
        }

        return [
            'source' => 'camara',
            'scope' => $year,
            'started_at_ms' => $startedAtMs,
            'generated_at' => now()->toIso8601String(),
            'year' => $year,
            'totals' => [
                'themed_bills_in_file' => 100,
                'bills_with_deputy_author' => array_sum(array_column($states, 0)),
                'states_with_data' => count($states),
            ],
            'checks' => ['author_rows_used' => 100, 'author_rows_without_uf' => 0],
            'states' => $out,
            'files' => [
                'themes' => ['url' => "https://dadosabertos.camara.leg.br/arquivos/proposicoesTemas/csv/proposicoesTemas-{$year}.csv", 'last_modified' => 'Wed, 21 Oct 2026 03:00:00 GMT'],
            ],
        ];
    }
}
