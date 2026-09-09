<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_publications', function (Blueprint $table) {

            $table->id();


            $table->foreignId('public_opportunity_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->string('publication_key', 100)
                ->unique();


            $table->string('publication_type', 60)
                ->nullable();


            $table->string('territory_id', 30)
                ->nullable()
                ->index();


            $table->date('gazette_date')
                ->nullable()
                ->index();


            $table->string('edition', 100)
                ->nullable();


            $table->text('gazette_url')
                ->nullable();

            $table->text('txt_url')
                ->nullable();


            // Trecho usado como evidência/revisão.
            $table->text('source_excerpt')
                ->nullable();


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_publications');
    }
};
