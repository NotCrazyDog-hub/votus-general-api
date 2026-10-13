<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migração NOVA (a migration original 2026_09_24_141041_create_candidacy_histories_table
 * NÃO é alterada — a tabela já existe em produção).
 *
 * `candidacy_histories.round` (NR_TURNO) continua existindo, como pede a
 * especificação, mas precisa de dois ajustes para receber o histórico da API:
 *
 * 1) Fica NULLABLE. A API entrega `eleicoesAnteriores` sem qualquer
 *    informação de turno, e a coluna antiga era NOT NULL. Inventar
 *    `round = 1` poluiria o dado — a especificação é explícita em não
 *    hacerlo. Com a coluna nullable, `round` simplesmente fica null até a
 *    etapa de resultados/segundo turno, e nada de falso é gravado.
 *
 * 2) O UNIQUE passa de `(candidacy_external_id, round)` para
 *    `(candidate_id, candidacy_external_id)`. Com `round` sempre null, o
 *    índice antigo deixa de proteger contra duplicata (em SQL, NULL nunca
 *    colide com NULL num unique), e reexecutar a sincronização criaria linhas
 *    repetidas. O novo par é o que realmente identifica uma candidatura
 *    anterior: o mesmo SQ_CANDIDATO não pode entrar duas vezes para o mesmo
 *    candidato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidacy_histories', function (Blueprint $table) {
            $table->dropUnique(
                'candidacy_histories_candidacy_external_id_round_unique'
            );
        });

        Schema::table('candidacy_histories', function (Blueprint $table) {
            $table->unsignedTinyInteger('round')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('candidacy_histories')->whereNull('round')->exists()) {
            throw new RuntimeException(
                'Não é possível reverter: existem históricos com round NULL.'
            );
        }

        Schema::table('candidacy_histories', function (Blueprint $table) {
            $table->unsignedTinyInteger('round')->nullable(false)->change();
        });

        Schema::table('candidacy_histories', function (Blueprint $table) {
            $table->unique(
                ['candidacy_external_id', 'round'],
                'candidacy_histories_candidacy_external_id_round_unique'
            );
        });
    }
};
