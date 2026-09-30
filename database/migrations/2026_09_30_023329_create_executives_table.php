<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executives', function (Blueprint $table) {
            $table->id();

            // Identificação
            $table->string('name');
            $table->string('display_name')->nullable();

            // Cargo
            $table->string('office');
            $table->string('level');
            $table->string('state', 2)->nullable();

            // Partido
            $table->string('party_acronym')->nullable();
            $table->string('party_name')->nullable();

            // Dados pessoais
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('occupation')->nullable();
            $table->text('education')->nullable();

            // Perfil
            $table->text('biography')->nullable();
            $table->string('photo_path')->nullable();

            // Mandato
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->boolean('is_current')->default(false);

            // Fonte dos dados
            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();

            // Identificador externo
            $table->string('external_id')->nullable();

            // Dados originais da fonte
            $table->jsonb('raw_data')->nullable();

            $table->timestamps();

            // Evita duplicar o mesmo ocupante pelo identificador da fonte
            $table->unique(
                ['external_id', 'office'],
                'executives_external_id_office_unique'
            );

            // Facilita consultas dos ocupantes atuais
            $table->index(['office', 'level', 'is_current']);
            $table->index(['state', 'office', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executives');
    }
};