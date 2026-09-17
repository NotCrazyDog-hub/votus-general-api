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
        Schema::create('fontes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->string('tipo_coleta'); // 'rss' | 'html'
            $table->string('url_base');
            $table->json('feeds')->nullable(); // categoria => URL do feed/página, quando a fonte tiver múltiplas categorias
            $table->boolean('ativa')->default(true);
            $table->unsignedSmallInteger('offset_minutos')->default(0);
            $table->unsignedInteger('limite_falhas')->default(5);
            $table->unsignedInteger('falhas_consecutivas')->default(0);
            $table->timestamp('ultima_falha_em')->nullable();
            $table->string('ultimo_erro', 500)->nullable();
            $table->timestamp('desativada_em')->nullable();
            $table->timestamp('ultima_coleta_em')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fontes');
    }
};
