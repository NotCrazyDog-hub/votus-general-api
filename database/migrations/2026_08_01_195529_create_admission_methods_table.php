<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'admission_methods',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('university_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('type');
                $table->string('name');

                $table->text('description')->nullable();
                $table->text('official_url')->nullable();

                $table->boolean('active')->default(true);
                $table->timestamp('verified_at')->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'university_id',
                        'type',
                        'name',
                    ],
                    'university_admission_method_unique'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_methods');
    }
};