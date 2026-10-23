<?php

namespace Tests\Feature\RouteAudit;

use App\Domains\Explanations\Models\TrustedSource;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class AdminAuthTest extends RouteAuditTestCase
{
    public function test_an_admin_logs_in_and_the_token_opens_the_admin_area(): void
    {
        $this->makeUser(admin: true);

        $response = $this->postJson('/api/admin/login', ['email' => 'admin@teste.local', 'password' => 'senha-de-teste-123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonMissingPath('user.password');

        $this->withToken($response->json('token'))
            ->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('email', 'admin@teste.local');
    }

    public function test_wrong_password_and_unknown_email_get_the_same_generic_error_and_no_token(): void
    {
        $this->makeUser(admin: true);

        $wrong = $this->postJson('/api/admin/login', ['email' => 'admin@teste.local', 'password' => 'errada'])
            ->assertStatus(422)->json('errors.email.0');
        $unknown = $this->postJson('/api/admin/login', ['email' => 'ninguem@teste.local', 'password' => 'errada'])
            ->assertStatus(422)->json('errors.email.0');

        // Mesma mensagem: não revela se o e-mail existe.
        $this->assertSame('Credenciais inválidas.', $wrong);
        $this->assertSame($wrong, $unknown);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_login_validates_the_payload(): void
    {
        $this->postJson('/api/admin/login', [])->assertStatus(422)->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/admin/login', ['email' => 'nao-e-email', 'password' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_a_non_admin_with_the_right_password_gets_403_and_no_token(): void
    {
        $this->makeUser(admin: false);

        $this->postJson('/api/admin/login', ['email' => 'usuario@teste.local', 'password' => 'senha-de-teste-123'])
            ->assertForbidden()
            ->assertJsonMissingPath('token');

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_admin_routes_reject_missing_and_non_admin_credentials(): void
    {
        $routes = [
            ['getJson', '/api/admin/me'],
            ['getJson', '/api/admin/dashboard'],
            ['postJson', '/api/admin/suggestions'],
            ['deleteJson', '/api/admin/news/1'],
            ['deleteJson', '/api/admin/proposals/1/permanent'],
            ['patchJson', '/api/admin/proposals/1/restore'],
            ['putJson', '/api/admin/trusted-sources/1'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->{$method}($uri)->assertUnauthorized();
        }

        // Rotas com route model binding resolvem o registro ANTES do
        // EnsureIsAdmin (que não está na prioridade de middleware): sem o
        // registro, um não-admin receberia 404 em vez de 403. O registro
        // existe para o teste medir a permissão.
        TrustedSource::create(['name' => 'Fonte', 'domain' => 'fonte.gov.br', 'base_url' => 'https://fonte.gov.br', 'is_active' => true]);

        Sanctum::actingAs($this->makeUser(admin: false));

        foreach ($routes as [$method, $uri]) {
            $this->{$method}($uri)->assertForbidden();
        }
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $this->makeUser(admin: true);
        $login = fn () => $this->postJson('/api/admin/login', ['email' => 'admin@teste.local', 'password' => 'senha-de-teste-123'])->json('token');

        $tokenA = $login();
        $tokenB = $login();
        $this->assertSame(2, DB::table('personal_access_tokens')->count());

        $this->withToken($tokenA)->postJson('/api/admin/logout')->assertOk();
        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        // Limpa o usuário autenticado em cache entre "requisições" do teste.
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenA)->getJson('/api/admin/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($tokenB)->getJson('/api/admin/me')->assertOk();
    }
}
