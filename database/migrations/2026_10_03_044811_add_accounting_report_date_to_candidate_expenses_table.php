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
        Schema::table('candidate_expenses', function (Blueprint $table) {
            // DT_PRESTACAO_CONTAS do TSE (já vinha em raw_data, nunca em
            // coluna própria). O TSE publica relatórios financeiros
            // periódicos durante a campanha — cada um recebe SQ_DESPESA
            // novos mesmo pra itens já declarados antes, então somar todos
            // os relatórios desde o início duplica o valor real. Esta
            // coluna permite filtrar só o relatório mais recente por
            // candidato nas consultas, sem apagar o histórico.
            $table->date('accounting_report_date')->nullable()->after('expense_origin');
            $table->index(['candidate_id', 'accounting_report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('candidate_expenses', function (Blueprint $table) {
            $table->dropIndex(['candidate_id', 'accounting_report_date']);
            $table->dropColumn('accounting_report_date');
        });
    }
};
