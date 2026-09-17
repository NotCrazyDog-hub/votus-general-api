<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            // Submissões da pesquisa (perguntas dinâmicas) agora guardam as
            // respostas em suggestion_answers; message só é obrigatório pra
            // quando o admin cadastra uma sugestão avulsa manualmente.
            $table->text('message')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggestions', function (Blueprint $table) {
            $table->text('message')->nullable(false)->change();
        });
    }
};
