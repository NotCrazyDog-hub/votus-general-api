<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id'); // SQ_CANDIDATO
            $table->string('ballot_number', 10)->nullable(); // NR_CANDIDATO
            $table->unsignedTinyInteger('round'); // NR_TURNO
            $table->string('coverage_scope', 5)->nullable(); // TP_ABRANGENCIA
            $table->string('state', 2); // SG_UF
            $table->unsignedSmallInteger('office_code')->nullable(); // CD_CARGO
            $table->string('office_name'); // DS_CARGO
            $table->string('civil_name'); // NM_CANDIDATO
            $table->string('ballot_name'); // NM_URNA_CANDIDATO
            $table->string('party_acronym', 20)->nullable(); // SG_PARTIDO
            $table->string('party_name')->nullable(); // NM_PARTIDO
            $table->string('education_level')->nullable(); // DS_GRAU_INSTRUCAO
            $table->string('occupation')->nullable(); // DS_OCUPACAO
            $table->string('race_color', 30)->nullable(); // DS_COR_RACA
            $table->unsignedSmallInteger('election_year');
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['external_id', 'round']);
            $table->index(['state', 'office_code', 'election_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};