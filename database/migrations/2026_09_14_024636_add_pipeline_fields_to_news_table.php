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
        Schema::table('news', function (Blueprint $table) {
            $table->foreignId('fonte_id')->nullable()->after('id')->constrained('fontes')->nullOnDelete();
            $table->text('conteudo_original')->nullable()->after('original_summary');
            $table->string('link_normalizado')->nullable()->unique()->after('url');
            $table->string('eixo')->nullable()->after('category');
            $table->string('status_resumo')->default('concluido')->after('ai_summary'); // pendente | em_processamento | concluido | falhou
            $table->unsignedTinyInteger('tentativas_resumo')->default(0)->after('status_resumo');
            $table->timestamp('ultima_tentativa_resumo_em')->nullable()->after('tentativas_resumo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news', function (Blueprint $table) {
            $table->dropUnique(['link_normalizado']);
        });

        Schema::table('news', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fonte_id');
            $table->dropColumn([
                'conteudo_original',
                'link_normalizado',
                'eixo',
                'status_resumo',
                'tentativas_resumo',
                'ultima_tentativa_resumo_em',
            ]);
        });
    }
};
