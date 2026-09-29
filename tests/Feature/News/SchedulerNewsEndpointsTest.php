<?php

namespace Tests\Feature\News;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchedulerNewsEndpointsTest extends NewsTestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.scheduler.token' => 'token-de-teste',
            'queue.default' => 'database',
        ]);

        Http::preventStrayRequests();
    }

    public function test_coletar_noticias_endpoint_rejects_requests_without_a_valid_token(): void
    {
        $this->postJson('/api/schedule/coletar-noticias')->assertStatus(401);
    }

    public function test_processar_fila_endpoint_rejects_requests_without_a_valid_token(): void
    {
        $this->postJson('/api/schedule/processar-fila-noticias')->assertStatus(401);
    }

    public function test_coletar_noticias_endpoint_runs_with_a_valid_token(): void
    {
        $response = $this->postJson('/api/schedule/coletar-noticias', [], [
            'X-Scheduler-Token' => 'token-de-teste',
        ]);

        $response->assertOk()->assertJsonPath('status', 'executado');
    }

    public function test_processar_fila_endpoint_runs_with_a_valid_token(): void
    {
        $response = $this->postJson('/api/schedule/processar-fila-noticias', [], [
            'X-Scheduler-Token' => 'token-de-teste',
        ]);

        $response->assertOk()->assertJsonPath('status', 'executado');
    }
}
