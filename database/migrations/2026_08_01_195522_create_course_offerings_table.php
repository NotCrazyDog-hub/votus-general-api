<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'course_offerings',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('campus_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('mec_course_code');

                $table->string('name');
                $table->string('normalized_name');

                $table->string('degree')->nullable();
                $table->string('area')->nullable();
                $table->string('modality')->nullable();
                $table->string('status')->nullable();

                $table->unsignedInteger(
                    'authorized_vacancies'
                )->nullable();

                $table->unsignedInteger(
                    'workload_hours'
                )->nullable();

                $table->string('source_name')->nullable();
                $table->date('source_updated_at')->nullable();

                $table->timestamps();

                $table->unique(
                    ['campus_id', 'mec_course_code'],
                    'campus_course_unique'
                );

                $table->index('normalized_name');
                $table->index('modality');
                $table->index('status');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offerings');
    }
};