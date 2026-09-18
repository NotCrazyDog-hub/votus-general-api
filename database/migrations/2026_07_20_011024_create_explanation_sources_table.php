<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('explanation_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('explanation_id')
                ->constrained('explanations')
                ->cascadeOnDelete();

            $table->foreignId('trusted_source_id')
                ->nullable()
                ->constrained('trusted_sources')
                ->nullOnDelete();

            $table->string('source_name');

            $table->text('source_url');

            $table->string('source_domain');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('explanation_sources');
    }
};