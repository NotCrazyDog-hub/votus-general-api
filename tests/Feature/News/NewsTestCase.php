<?php

namespace Tests\Feature\News;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MigratesTestSchema;
use Tests\TestCase;

/**
 * Base dos testes do pipeline de notícias.
 *
 * O migrate:fresh completo quebra hoje em banco novo: duas migrations criam a
 * mesma tabela personal_access_tokens (2026_07_18_212105 e 2026_07_27_155713),
 * então TODO teste com RefreshDatabase falhava antes mesmo de rodar. Em vez
 * de mexer nas migrations (o banco de produção já as tem aplicadas), os
 * testes de notícias migram só as tabelas de que precisam — via
 * MigratesTestSchema, que traz a união com as tabelas de candidatos porque o
 * migrate:fresh só roda uma vez por processo.
 */
abstract class NewsTestCase extends TestCase
{
    use MigratesTestSchema, RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return $this->testSchemaMigrateFreshUsing();
    }
}
