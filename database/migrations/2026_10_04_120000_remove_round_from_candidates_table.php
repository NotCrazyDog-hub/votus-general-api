<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migração NOVA (a tabela `candidates` é de produção — a migration original
 * 2026_09_17_140519_create_candidates_table NÃO pode ser alterada).
 *
 * Remove `candidates.round` (NR_TURNO).
 *
 * Por que remover: a fonte de dados deixa de ser o CSV de Dados Abertos do
 * TSE e passa a ser a API DivulgaCandContas, que não expõe turno nenhum. Não
 * existe mais como preencher essa coluna de forma verdadeira, e chutar
 * `round = 1` contaminaria o dado com um valor inventado — é exatamente o que
 * a migração para a API precisa evitar. O segundo turno será tratado depois,
 * a partir da fonte de resultados do TSE.
 *
 * `candidacy_histories.round` NÃO é removida aqui (continua existindo para a
 * etapa futura de resultados).
 *
 * Sobre o índice: a migration original criou `unique(external_id, round)`.
 * Com `round` fora, sobra um unique em duas colunas sem a segunda — e o
 * external_id sozinho NÃO pode virar unique, porque no esquema antigo o mesmo
 * SQ_CANDIDATO aparece uma vez por turno (1º e 2º), ou seja, o banco de
 * produção pode (e tem) external_id repetido. Transformar isso em unique
 * faria a migration explodir em produção.
 *
 * Então o unique é trocado por um índice comum em `external_id`: a
 * idempotência da sincronização é garantida na aplicação
 * (`CandidateSyncService`, que resolve por external_id + ano), e não por uma
 * restrição que os dados históricos não respeitam.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropUnique(['external_id', 'round']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('round');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->index('external_id');
        });
    }

    public function down(): void
    {
        // Valores de NR_TURNO não são mais fornecidos pela API; restored = 0
        // apenas satisfaz o NOT NULL da coluna original para permitir o
        // rollback. O dado real de turno não é recuperável por aqui.
        Schema::table('candidates', function (Blueprint $table) {
            $table->unsignedTinyInteger('round')->default(0);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->unique(['external_id', 'round']);
        });
    }
};
