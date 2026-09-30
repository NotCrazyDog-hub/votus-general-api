<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('executive_id')
                ->constrained('executives')
                ->cascadeOnDelete();

            // Identificação da ação
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('action_type')->nullable();

            // Quando ocorreu/publicada
            $table->date('occurred_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // Localização
            $table->string('location')->nullable();

            // Fonte
            $table->string('source_name');
            $table->string('source_type')->nullable();
            $table->text('source_url');

            // Informações extraídas pela IA
            $table->json('entities')->nullable();
            $table->json('raw_data')->nullable();

            // Controle da análise automática
            $table->decimal('relevance_score', 5, 2)->nullable();
            $table->string('analysis_status')->default('pending');
            $table->timestamp('analyzed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'executive_id',
                'source_url',
            ]);

            $table->index('action_type');
            $table->index('occurred_at');
            $table->index('published_at');
            $table->index('source_type');
            $table->index('analysis_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_actions');
    }
};