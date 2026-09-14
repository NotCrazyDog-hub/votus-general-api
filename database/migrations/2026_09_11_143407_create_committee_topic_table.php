<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_topic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->decimal('match_score', 5, 4)->nullable();
            $table->string('match_method')->default('text_similarity');
            $table->boolean('reviewed')->default(false);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['committee_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_topic');
    }
};