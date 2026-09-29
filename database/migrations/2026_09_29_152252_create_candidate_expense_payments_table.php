<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_expense_id')->constrained('candidate_expenses')->cascadeOnDelete();
            $table->unsignedBigInteger('expense_external_id'); // SQ_DESPESA
            $table->unsignedBigInteger('installment_external_id')->nullable(); // SQ_PARCELAMENTO_DESPESA
            $table->date('payment_date'); // DT_PAGTO_DESPESA
            $table->decimal('paid_amount', 12, 2); // VR_PAGTO_DESPESA
            $table->string('resource_type')->nullable(); // DS_ESPECIE_RECURSO
            $table->string('document_type')->nullable(); // DS_TIPO_DOCUMENTO
            $table->string('document_number', 50)->nullable(); // NR_DOCUMENTO
            $table->string('expense_source')->nullable(); // DS_FONTE_DESPESA
            $table->string('expense_origin')->nullable(); // DS_ORIGEM_DESPESA
            $table->string('expense_nature')->nullable(); // DS_NATUREZA_DESPESA
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index('expense_external_id');
            $table->index('payment_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_expense_payments');
    }
};