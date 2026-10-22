<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_plan_themes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('government_plan_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('government_plan_theme_id')->constrained('government_plan_themes')->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->text('source_excerpt')->nullable();
            $table->string('source_reference')->nullable();
            $table->string('source_url', 2048);
            $table->string('fingerprint', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'fingerprint']);
            $table->index(['government_plan_theme_id', 'candidate_id']);
        });

        Schema::create('government_plan_comparisons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_plan_theme_id')->constrained('government_plan_themes')->cascadeOnDelete();
            $table->foreignId('candidate_one_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('candidate_two_id')->constrained('candidates')->cascadeOnDelete();
            $table->text('summary');
            $table->json('similarities')->nullable();
            $table->json('differences')->nullable();
            $table->json('candidate_summaries')->nullable();
            $table->timestamps();

            $table->unique([
                'government_plan_theme_id',
                'candidate_one_id',
                'candidate_two_id',
            ], 'government_plan_comparison_unique_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_plan_comparisons');
        Schema::dropIfExists('government_plan_proposals');
        Schema::dropIfExists('government_plan_themes');
    }
};
