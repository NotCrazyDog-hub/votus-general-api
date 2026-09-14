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
        Schema::create('committee_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained('committees')->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained('topics')->cascadeOnDelete();
            $table->decimal('match_score', 5, 4)->nullable();
            $table->string('match_method')->nullable(); // 'text_similarity' | 'ai_review' | 'manual'
            $table->boolean('reviewed')->default(false);
            $table->string('reviewed_source')->nullable(); // 'text_similarity' | 'ai_review' | 'manual'
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->text('ai_reasoning')->nullable(); // opcional: guarda a justificativa do LLM
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
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
