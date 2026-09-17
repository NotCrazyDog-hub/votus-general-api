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
        Schema::create('suggestion_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained()->cascadeOnDelete();
            // cascadeOnDelete: apagar uma pergunta no admin também apaga as
            // respostas antigas dela — é o comportamento pedido ("apagar,
            // deletar ou atualizar"), não faz sentido manter resposta órfã.
            $table->foreignId('suggestion_question_id')->constrained()->cascadeOnDelete();
            $table->text('answer');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suggestion_answers');
    }
};
