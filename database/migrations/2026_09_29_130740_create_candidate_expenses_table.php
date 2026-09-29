<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->unsignedBigInteger('expense_external_id')->unique(); // SQ_DESPESA
            $table->date('expense_date'); // DT_DESPESA
            $table->string('description'); // DS_DESPESA
            $table->decimal('amount', 12, 2); // VR_DESPESA_CONTRATADA
            $table->string('supplier_document', 14)->nullable(); // NR_CPF_CNPJ_FORNECEDOR
            $table->string('supplier_name')->nullable(); // NM_FORNECEDOR
            $table->string('supplier_type')->nullable(); // DS_TIPO_FORNECEDOR
            $table->string('document_type')->nullable(); // DS_TIPO_DOCUMENTO
            $table->string('document_number', 50)->nullable(); // NR_DOCUMENTO
            $table->string('expense_origin')->nullable(); // DS_ORIGEM_DESPESA
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_expenses');
    }
};