<?php

namespace Tests\Feature\News;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base dos testes do pipeline de notícias.
 *
 * O migrate:fresh completo quebra hoje em banco novo: duas migrations criam a
 * mesma tabela personal_access_tokens (2026_07_18_212105 e 2026_07_27_155713),
 * então TODO teste com RefreshDatabase falhava antes mesmo de rodar. Em vez
 * de mexer nas migrations (o banco de produção já as tem aplicadas), os
 * testes de notícias migram só as tabelas de que precisam.
 */
abstract class NewsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function migrateFreshUsing()
    {
        return [
            '--drop-views' => false,
            '--drop-types' => false,
            '--seed' => false,
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/0001_01_01_000002_create_jobs_table.php',
                'database/migrations/2026_07_18_212105_create_personal_access_tokens_table.php',
                'database/migrations/2026_07_25_211607_create_cache_table.php',
                'database/migrations/2026_08_27_133621_create_news_table.php',
                'database/migrations/2026_09_10_144526_add_logo_image_columns_to_news_table.php',
                'database/migrations/2026_09_14_024635_create_fontes_table.php',
                'database/migrations/2026_09_14_024636_add_pipeline_fields_to_news_table.php',
                'database/migrations/2026_09_16_134151_add_is_admin_to_users_table.php',
                'database/migrations/2026_09_16_143121_add_erro_resumo_to_news_table.php',
            ],
        ];
    }
}
