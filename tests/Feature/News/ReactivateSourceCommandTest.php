<?php

namespace Tests\Feature\News;

use App\Models\NewsSource;
use Tests\TestCase;

class ReactivateSourceCommandTest extends NewsTestCase
{

    public function test_it_reactivates_a_disabled_source_and_resets_the_failure_counter(): void
    {
        $source = NewsSource::factory()->create([
            'ativa' => false,
            'falhas_consecutivas' => 5,
            'ultimo_erro' => 'algum erro antigo',
            'desativada_em' => now(),
        ]);

        $this->artisan('sources:reactivate', ['slug' => $source->slug])
            ->assertExitCode(0);

        $source->refresh();
        $this->assertTrue($source->ativa);
        $this->assertSame(0, $source->falhas_consecutivas);
        $this->assertNull($source->ultimo_erro);
        $this->assertNull($source->desativada_em);
    }

    public function test_it_fails_gracefully_for_an_unknown_slug(): void
    {
        $this->artisan('sources:reactivate', ['slug' => 'fonte-inexistente'])
            ->assertExitCode(1);
    }
}
