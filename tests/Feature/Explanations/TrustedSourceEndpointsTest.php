<?php

namespace Tests\Feature\Explanations;

use App\Models\TrustedSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedSourceEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_register_a_domain_that_already_exists(): void
    {
        TrustedSource::create(['name' => 'Câmara dos Deputados', 'domain' => 'camara.leg.br', 'is_active' => true]);

        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/trusted-sources', [
            'name' => 'camara',
            'base_url' => 'https://www.camara.leg.br/noticias/545049-alguma-materia?utm_source=chatgpt.com',
        ]);

        // Antes dava erro 500 cru do banco (unique constraint) — precisa
        // virar uma resposta 422 legível pro admin, sem vazar detalhes de
        // SQL/conexão.
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'O domínio "camara.leg.br" já está cadastrado como fonte confiável ("Câmara dos Deputados").']);
        $this->assertSame(1, TrustedSource::where('domain', 'camara.leg.br')->count());
    }

    public function test_admin_can_register_a_source_and_base_url_is_normalized_to_the_root(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/trusted-sources', [
            'name' => 'Câmara dos Deputados',
            'base_url' => 'https://www.camara.leg.br/noticias/545049-alguma-materia?utm_source=chatgpt.com',
        ]);

        $response->assertCreated();
        $this->assertSame('camara.leg.br', $response->json('domain'));
        $this->assertSame('https://www.camara.leg.br/', $response->json('base_url'));
    }

    public function test_admin_cannot_update_a_source_to_a_domain_used_by_another_source(): void
    {
        TrustedSource::create(['name' => 'Fonte A', 'domain' => 'fontea.com', 'is_active' => true]);
        $fonteB = TrustedSource::create(['name' => 'Fonte B', 'domain' => 'fonteb.com', 'is_active' => true]);

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/trusted-sources/{$fonteB->id}", [
                'name' => 'Fonte B',
                'domain' => 'fontea.com',
                'is_active' => true,
            ])
            ->assertStatus(422);

        $this->assertSame('fonteb.com', $fonteB->fresh()->domain);
    }
}
