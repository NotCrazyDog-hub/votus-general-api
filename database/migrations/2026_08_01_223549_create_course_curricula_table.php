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
        Schema::create('course_curricula', function (Blueprint $table) {
        $table->id();

        $table->foreignId('course_offering_id')
            ->constrained()
            ->cascadeOnDelete();

        $table->string('name');
        $table->unsignedSmallInteger('version_year')->nullable();

        $table->unsignedInteger('total_hours')->nullable();
        $table->unsignedTinyInteger('duration_semesters')->nullable();

        $table->text('official_url')->nullable();

        $table->boolean('active')->default(true);
        $table->timestamp('verified_at')->nullable();

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_curricula');
    }
};
