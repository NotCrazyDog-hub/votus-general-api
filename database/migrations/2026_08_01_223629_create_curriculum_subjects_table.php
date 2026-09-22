<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'curriculum_subjects',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('course_curriculum_id')
                    ->constrained('course_curricula')
                    ->cascadeOnDelete();

                $table->string('name');

                $table->unsignedTinyInteger('semester')
                    ->nullable();

                $table->unsignedInteger('workload_hours')
                    ->nullable();

                /*
                 * Exemplos:
                 * obrigatoria
                 * optativa
                 * extensao
                 */
                $table->string('type')->nullable();

                $table->unsignedSmallInteger('position')
                    ->default(0);

                $table->timestamps();

                $table->index(
                    [
                        'course_curriculum_id',
                        'semester',
                    ],
                    'curriculum_subjects_curriculum_semester_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_subjects');
    }
};