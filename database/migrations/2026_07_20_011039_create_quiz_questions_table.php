<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('explanation_id')
                ->constrained('explanations')
                ->cascadeOnDelete();

            $table->text('question');

            $table->text('explanation')
                ->nullable();

            $table->unsignedSmallInteger('position');

            $table->unsignedInteger(
                'based_on_content_version'
            );

            $table->timestamps();

            $table->unique([
                'explanation_id',
                'position',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};