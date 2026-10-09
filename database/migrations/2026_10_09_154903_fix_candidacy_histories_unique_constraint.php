<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidacy_histories', function (Blueprint $table) {
            $table->dropUnique(
                'candidacy_histories_candidacy_external_id_round_unique'
            );

            $table->unique(
                ['candidacy_external_id', 'election_year', 'round'],
                'candidacy_histories_external_year_round_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('candidacy_histories', function (Blueprint $table) {
            $table->dropUnique(
                'candidacy_histories_external_year_round_unique'
            );

            $table->unique(
                ['candidacy_external_id', 'round'],
                'candidacy_histories_candidacy_external_id_round_unique'
            );
        });
    }
};