<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explanations', function (Blueprint $table) {
            $table->id();

            $table->string('title');

            $table->string('slug')->unique();

            $table->string('question_title');

            $table->string('category');

            $table->text('summary')->nullable();

            $table->text('what_is')->nullable();

            $table->text('purpose')->nullable();

            $table->text('practical_role')->nullable();

            $table->text('why_it_matters')->nullable();

            $table->text('citizen_impact')->nullable();

            $table->text('example')->nullable();

            $table->string('status')
                ->default('generating');

            $table->unsignedInteger('content_version')
                ->default(1);

            $table->text('generation_error')
                ->nullable();

            $table->timestamp('published_at')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explanations');
    }
};