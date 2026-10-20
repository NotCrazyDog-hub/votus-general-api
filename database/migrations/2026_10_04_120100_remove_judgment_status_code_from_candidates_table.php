<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migração NOVA (a migration original que criou as colunas,
 * 2026_09_29_142224_add_judgment_status_to_candidates_table, não é alterada).
 *
 * Remove `candidates.judgment_status_code` (CD_SITUACAO_JULGAMENTO).
 *
 * A API DivulgaCandContas devolve a situação jurídica como texto
 * (`descricaoSituacao`, ex.: "Deferido"), não como código. Guardar o código
 * exigiria uma tabela de tradução que não existe e não deve ser criada aqui —
 * basta o texto, que já é o que `judgment_status` (mantida) armazena.
 *
 * `judgment_status` permanece: é dela que saem os scopes `approved()` /
 * `rejected()`, usados nas listagens públicas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Estado final das duas colunas, garantido aqui em vez de depender
        // apenas da migration histórica.
        //
        // Motivo: 2026_09_29_142224 cria `judgment_status_code` e
        // `judgment_status` com ->after('candidacy_status') — mas
        // `candidacy_status` nunca existiu em `candidates` (ela é coluna de
        // candidacy_histories). No Postgres, que é o banco de produção,
        // `after()` é ignorado e a migration passou sem erro. No MySQL
        // (WAMP/XAMPP, usado nos testes) `after()` é real e a migration falha.
        //
        // Como essa migration já foi aplicada em produção e não pode ser
        // alterada, esta nova migration entrega o estado final diretamente:
        // em produção apenas remove a coluna de código; em MySQL, onde a
        // antiga nem rodou, cria `judgment_status` antes de seguir.
        Schema::table('candidates', function (Blueprint $table) {
            if (! Schema::hasColumn('candidates', 'judgment_status')) {
                $table->string('judgment_status')->nullable();
            }
        });

        Schema::table('candidates', function (Blueprint $table) {
            if (Schema::hasColumn('candidates', 'judgment_status_code')) {
                $table->dropColumn('judgment_status_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            if (! Schema::hasColumn('candidates', 'judgment_status_code')) {
                $table->smallInteger('judgment_status_code')->nullable();
            }
        });
    }
};
