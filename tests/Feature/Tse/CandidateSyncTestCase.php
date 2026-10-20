<?php

namespace Tests\Feature\Tse;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MigratesTestSchema;
use Tests\TestCase;

/**
 * Base dos testes do módulo de candidatos (fonte: API DivulgaCandContas).
 *
 * Roda contra o MySQL local (WAMP/XAMPP) configurado em `.env.testing` —
 * nunca contra Supabase/produção — ou, sem `--env=testing`, contra o SQLite
 * que o phpunit.xml aponta. Os testes não dependem do driver.
 *
 * O `migrate:fresh` completo não serve aqui pelo mesmo motivo descrito em
 * NewsTestCase: a migration 2026_09_29_142224 usa
 * `->after('candidacy_status')`, coluna que nunca existiu em `candidates`.
 * No Postgres (produção) `after()` é ignorado e passa; no MySQL dos testes
 * ela quebra. Como migrções antigas não podem ser alteradas, migramos só os
 * caminhos necessários — via MigratesTestSchema (união com as tabelas de
 * notícias, porque o migrate:fresh roda só uma vez por processo).
 *
 * Tudo roda com `Storage::fake()`, então nenhuma requisição ao Supabase
 * acontece nos testes.
 */
abstract class CandidateSyncTestCase extends TestCase
{
    use MigratesTestSchema, RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return $this->testSchemaMigrateFreshUsing();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Nenhum teste pode tocar Supabase: fotos e documentos vão para o
        // disco local fake.
        Storage::fake('supabase');

        // CandidateService guarda contagens e partidos por 1h em cache de
        // DISCO (`Cache::store('file')`) de propósito — e disco sobrevive entre
        // testes. Sem este flush, um teste veria o resultado calculado por outro
        // (ou por uma execução anterior) e o resultado ficaria não determinístico.
        Cache::store('file')->flush();
    }

    protected function baseUrl(): string
    {
        return config('services.tse.divulgacandcontas.base_url');
    }

    protected function arquivoUrl(): string
    {
        return config('services.tse.divulgacandcontas.arquivo_url');
    }

    protected function electionId(): string
    {
        return '20322002026';
    }
}
