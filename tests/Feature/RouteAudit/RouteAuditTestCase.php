<?php

namespace Tests\Feature\RouteAudit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MigratesTestSchema;
use Tests\TestCase;

/**
 * Base da auditoria das rotas de escrita (POST/PUT/PATCH/DELETE) e do login
 * de administrador. Roda SÓ no SQLite em memória do phpunit.xml, com
 * registros descartáveis criados pelo próprio teste — nunca contra o banco
 * de produção (o .env local aponta para o Supabase).
 */
abstract class RouteAuditTestCase extends TestCase
{
    use MigratesTestSchema, RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return $this->testSchemaMigrateFreshUsing();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Trava: se a configuração de teste falhar e o app apontar para o
        // Postgres, para antes de qualquer gravação.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        // Nenhuma chamada externa real (n8n, Groq, sites).
        Http::preventStrayRequests();
    }

    protected function makeUser(bool $admin, ?string $email = null): User
    {
        return User::create([
            'name' => $admin ? 'Admin Teste' : 'Usuário Teste',
            'email' => $email ?? ($admin ? 'admin@teste.local' : 'usuario@teste.local'),
            'password' => Hash::make('senha-de-teste-123'),
            'is_admin' => $admin,
        ]);
    }
}
