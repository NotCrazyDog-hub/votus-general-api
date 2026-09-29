<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->smallInteger('judgment_status_code')->nullable()->after('candidacy_status'); // CD_SITUACAO_JULGAMENTO
            $table->string('judgment_status')->nullable()->after('judgment_status_code'); // DS_SITUACAO_JULGAMENTO
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn(['judgment_status_code', 'judgment_status']);
        });
    }
};