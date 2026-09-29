<?php

namespace Tests\Feature\News;

use App\Models\Fonte;
use Tests\TestCase;

class FontesReativarCommandTest extends NewsTestCase
{

    public function test_it_reactivates_a_disabled_source_and_resets_the_failure_counter(): void
    {
        $fonte = Fonte::factory()->create([
            'ativa' => false,
            'falhas_consecutivas' => 5,
            'ultimo_erro' => 'algum erro antigo',
            'desativada_em' => now(),
        ]);

        $this->artisan('fontes:reativar', ['slug' => $fonte->slug])
            ->assertExitCode(0);

        $fonte->refresh();
        $this->assertTrue($fonte->ativa);
        $this->assertSame(0, $fonte->falhas_consecutivas);
        $this->assertNull($fonte->ultimo_erro);
        $this->assertNull($fonte->desativada_em);
    }

    public function test_it_fails_gracefully_for_an_unknown_slug(): void
    {
        $this->artisan('fontes:reativar', ['slug' => 'fonte-inexistente'])
            ->assertExitCode(1);
    }
}
