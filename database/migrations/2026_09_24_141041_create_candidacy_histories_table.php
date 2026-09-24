<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidacy_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->unsignedBigInteger('candidacy_external_id'); // SQ_CANDIDATO
            $table->unsignedSmallInteger('election_year'); // ANO_ELEICAO
            $table->unsignedTinyInteger('round'); // NR_TURNO
            $table->string('state', 2); // SG_UF
            $table->string('office_name'); // DS_CARGO
            $table->string('ballot_number', 10)->nullable(); // NR_CANDIDATO
            $table->string('party_acronym', 20)->nullable(); // SG_PARTIDO
            $table->string('party_name')->nullable(); // NM_PARTIDO
            $table->string('candidacy_status')->nullable(); // DS_SITUACAO_CANDIDATURA
            $table->string('result_status')->nullable(); // DS_SIT_TOT_TURNO
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['candidacy_external_id', 'round']);
            $table->index(['candidate_id', 'election_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidacy_histories');
    }
};