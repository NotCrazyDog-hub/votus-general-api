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
            $table->decimal('ai_confidence', 5, 4)->nullable();
            $table->text('ai_reasoning')->nullable();
            $table->timestamps();

            $table->unique(['committee_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_topic');
    }
};