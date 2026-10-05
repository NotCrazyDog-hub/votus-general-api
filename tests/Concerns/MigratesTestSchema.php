<?php

namespace Tests\Concerns;

/**
 * Caminho de migrations compartilhado por todas as bases de teste que não
 * conseguem usar o `migrate:fresh` completo.
 *
 * Dois motivos para migrar só um subconjunto:
 *
 * 1. O banco completo NÃO migra: `2026_07_18_212105` e `2026_07_27_155713`
 *    criam a MESMA tabela `personal_access_tokens`, então um `migrate:fresh`
 *    sem filtro quebra antes do primeiro teste. A tabela duplicada fica de fora
 *    daqui.
 * 2. `2026_09_29_142224` usa `->after('candidacy_status')`, coluna que nunca
 *    existiu em `candidates`. Em Postgres `after()` é ignorado e passa; no
 *    MySQL/SQLite dos testes a migration falha.
 *
 * Por quê um ÚNICO subconjunto: `RefreshDatabaseState::$migrated` faz o
 * `migrate:fresh` rodar UMA vez por processo — apenas na primeira classe com
 * `RefreshDatabase`. Se cada base declarasse só as próprias tabelas, a
 * primeira a rodar definiria o schema e a segunda morreria com
 * "table doesn't exist". Um subconjunto (união) único torna a ordem das classes
 * irrelevante.
 */
trait MigratesTestSchema
{
    /**
     * Argumentos do `artisan migrate:fresh` para o subconjunto unificado.
     *
     * O nome NÃO pode ser `migrateFreshUsing`: esse já vem do trait
     * `RefreshDatabase` e PHP aborta com "collision" quando dois traits trazem
     * o mesmo método. Cada classe delega do seu próprio `migrateFreshUsing()`.
     *
     * @return array<string, mixed>
     */
    protected function testSchemaMigrateFreshUsing(): array
    {
        return [
            '--drop-views' => false,
            '--drop-types' => false,
            '--seed' => false,
            '--path' => [
                // base
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/0001_01_01_000002_create_jobs_table.php',
                'database/migrations/2026_07_18_212105_create_personal_access_tokens_table.php',
                'database/migrations/2026_07_25_211607_create_cache_table.php',

                // notícias (NewsTestCase)
                'database/migrations/2026_08_27_133621_create_news_table.php',
                'database/migrations/2026_09_10_144526_add_logo_image_columns_to_news_table.php',
                'database/migrations/2026_09_14_024635_create_fontes_table.php',
                'database/migrations/2026_09_14_024636_add_pipeline_fields_to_news_table.php',
                'database/migrations/2026_09_16_134151_add_is_admin_to_users_table.php',
                'database/migrations/2026_09_16_143121_add_erro_resumo_to_news_table.php',

                // candidatos (CandidateSyncTestCase)
                'database/migrations/2026_07_16_173107_create_legislators_table.php',
                'database/migrations/2026_09_17_140519_create_candidates_table.php',
                'database/migrations/2026_09_17_142127_change_coverage_scope_column_in_candidates_table.php',
                'database/migrations/2026_09_17_145723_add_cpf_to_candidates_table.php',
                'database/migrations/2026_09_18_130555_add_photo_path_to_candidates_table.php',
                'database/migrations/2026_09_18_135515_add_running_mate_of_id_to_candidates_table.php',
                'database/migrations/2026_09_22_142744_add_proposal_document_path_to_candidates_table.php',
                'database/migrations/2026_09_24_141041_create_candidacy_histories_table.php',
                'database/migrations/2026_09_29_130740_create_candidate_expenses_table.php',
                'database/migrations/2026_09_29_152252_create_candidate_expense_payments_table.php',
                'database/migrations/2026_10_04_120000_remove_round_from_candidates_table.php',
                'database/migrations/2026_10_04_120100_remove_judgment_status_code_from_candidates_table.php',
                'database/migrations/2026_10_04_120200_make_round_nullable_on_candidacy_histories_table.php',
            ],
        ];
    }
}
