<?php

namespace Tests\Feature\Radar;

use App\Domains\Radar\Jobs\RefreshRadarJob;
use App\Domains\Radar\Services\RadarRefresher;
use App\Domains\Radar\Services\RadarUfs;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

class RadarEndpointsTest extends RadarTestCase
{
    public function test_without_data_it_answers_pending_and_dispatches_background_jobs_once(): void
    {
        Queue::fake();

        $this->getJson('/api/radar/states')
            ->assertOk()
            ->assertJsonPath('camara.status', 'pending')
            ->assertJsonPath('ibge.status', 'pending')
            ->assertJsonPath('states.0.camara', null)
            ->assertJsonCount(27, 'states');

        $expected = count(RadarRefresher::years()) + 1; // anos da Câmara + IBGE
        Queue::assertPushedOn('radar', RefreshRadarJob::class);
        Queue::assertPushed(RefreshRadarJob::class, $expected);

        // Uma segunda leitura logo em seguida não despacha de novo.
        $this->getJson('/api/radar/states')->assertOk();
        Queue::assertPushed(RefreshRadarJob::class, $expected);
    }

    public function test_overview_for_one_year_lists_top_topics_with_share(): void
    {
        Queue::fake();
        $this->store()->publish('camara', 2025, $this->camaraResult(2025, [
            'CE' => [10, ['Saúde' => 6, 'Educação' => 5, 'Segurança' => 2, 'Cultura' => 1]],
        ]));

        $response = $this->getJson('/api/radar/states?period=2025')->assertOk()
            ->assertJsonPath('camara.status', 'ok')
            ->assertJsonPath('period.key', '2025');

        $ce = collect($response->json('states'))->firstWhere('uf', 'CE');
        $this->assertSame(10, $ce['camara']['propositions']);
        $this->assertSame(['Saúde', 'Educação', 'Segurança'], array_column($ce['camara']['top_topics'], 'name'));
        $this->assertEquals(60.0, $ce['camara']['top_topics'][0]['share']);

        // UF sem proposição no ano: zero, não ausente
        $sp = collect($response->json('states'))->firstWhere('uf', 'SP');
        $this->assertSame(0, $sp['camara']['propositions']);
    }

    public function test_legislature_view_sums_years_and_is_withheld_while_a_year_is_missing(): void
    {
        Queue::fake();
        $years = RadarRefresher::years();

        foreach (array_slice($years, 0, -1) as $year) {
            $this->store()->publish('camara', $year, $this->camaraResult($year, ['CE' => [10, ['Saúde' => 4, 'Educação' => 1]]]));
        }

        // Falta o último ano: nada de total parcial.
        $this->getJson('/api/radar/states/CE')->assertOk()
            ->assertJsonPath('camara.status', 'pending')
            ->assertJsonPath('camara.missing_years', [end($years)])
            ->assertJsonPath('camara.data', null);

        $this->store()->publish('camara', end($years), $this->camaraResult(end($years), ['CE' => [5, ['Educação' => 5]]]));

        $data = $this->getJson('/api/radar/states/CE')->assertOk()
            ->assertJsonPath('camara.status', 'ok')
            ->json('camara.data');

        $n = count($years) - 1;
        $this->assertSame(10 * $n + 5, $data['propositions']);
        $this->assertNull($data['deputies']);
        $this->assertSame(['Saúde', 'Educação'], array_column($data['topics'], 'name'));
        $this->assertSame([4 * $n, 1 * $n + 5], array_column($data['topics'], 'count'));
    }

    public function test_state_detail_keeps_camara_senado_and_ibge_separate(): void
    {
        Queue::fake();
        $this->store()->publish('camara', 2025, $this->camaraResult(2025, ['AM' => [8, ['Saúde' => 3]]]));
        $this->store()->publish('senado', 'AM', [
            'source' => 'senado', 'scope' => 'AM', 'started_at_ms' => 1, 'generated_at' => now()->toIso8601String(),
            'uf' => 'AM', 'since' => '2023-02-01',
            'senators' => [['code' => '1', 'name' => 'Senador', 'participation' => 'Titular']],
            'checks' => ['bills_found' => 2, 'bills_classified_fetch_ok' => 2, 'details_failed' => 0, 'details_failed_bills' => []],
            'years' => [2025 => ['propositions' => 2, 'without_topic' => 0, 'topics' => [['name' => 'Saúde', 'count' => 2]]]],
            'legislature' => ['propositions' => 2, 'without_topic' => 0, 'topics' => [['name' => 'Saúde', 'count' => 2]]],
        ]);

        $this->getJson('/api/radar/states/am?period=2025')->assertOk()
            ->assertJsonPath('state.uf', 'AM')
            ->assertJsonPath('state.ibge_code', 13)
            ->assertJsonPath('camara.data.propositions', 8)
            ->assertJsonPath('camara.data.deputies', 5)
            ->assertJsonPath('senado.data.propositions', 2)
            ->assertJsonPath('senado.data.small_sample', true)
            ->assertJsonMissingPath('senado.data.topics.0.share')
            ->assertJsonPath('ibge.status', 'pending')
            ->assertJsonPath('ibge.values', null);

        // Um ano sem proposição no Senado vira zero, não erro.
        $this->getJson('/api/radar/states/AM?period=2024')->assertOk()
            ->assertJsonPath('senado.data.propositions', 0);
    }

    public function test_it_shows_when_the_last_update_failed_without_exposing_the_error(): void
    {
        Queue::fake();
        $this->store()->publish('camara', 2025, $this->camaraResult(2025, ['CE' => [10, ['Saúde' => 4]]]));
        $this->travel(1)->minutes();
        $this->store()->recordFailure('camara', 2025, 'SQLSTATE detalhe interno');

        $response = $this->getJson('/api/radar/states/CE?period=2025')->assertOk()
            ->assertJsonPath('camara.data.propositions', 10);

        $this->assertNotNull($response->json('camara.last_failed_at'));
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        Queue::fake();

        $this->getJson('/api/radar/states?period=1999')->assertStatus(422);
        $this->getJson('/api/radar/states?period=2025abc')->assertStatus(422);
        $this->getJson('/api/radar/states/ZZ')->assertNotFound();
        $this->getJson('/api/radar/states/CE?period=x')->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_existing_public_routes_are_still_registered(): void
    {
        foreach (['api/deputies', 'api/senators', 'api/news', 'api/governors', 'api/state-deputies'] as $uri) {
            $this->assertNotNull(
                collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === $uri && in_array('GET', $r->methods())),
                "rota {$uri} sumiu",
            );
        }

        $this->assertCount(27, RadarUfs::ALL);
    }
}
