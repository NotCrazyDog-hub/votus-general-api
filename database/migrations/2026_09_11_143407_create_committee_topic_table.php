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
        Schema::table('committee_topic', function (Blueprint $table) {
            $table->dropColumn('reviewed_by'); // se já tiver rodado a versão anterior
            $table->string('reviewed_source')->nullable(); // 'text_similarity' | 'ai_review' | 'manual'
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->text('ai_reasoning')->nullable(); // opcional: guarda a justificativa do LLM
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('committee_topic');
    }
};
