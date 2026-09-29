<?php

namespace App\Console\Commands;

use App\Models\CandidateExpense;
use App\Models\CandidateExpensePayment;
use App\Services\TseCandidateExpensePaymentsCsvService;
use Illuminate\Console\Command;

class SyncCandidateExpensePaymentsTse extends Command
{
    // php artisan sync:candidate-expense-payments-tse storage/app/tse/despesas_pagas_candidatos_2026_CE.csv --uf=CE
    protected $signature = 'sync:candidate-expense-payments-tse
        {file : Caminho do arquivo CSV de despesas pagas}
        {--uf=CE : Sigla da UF a importar}';

    protected $description = 'Importa despesas pagas dos candidatos a partir do CSV do TSE';

    public function handle(TseCandidateExpensePaymentsCsvService $csv): int
    {
        $filePath = $this->argument('file');
        $uf = $this->option('uf');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Importando despesas pagas de {$uf}...");

        $totalLines = max(0, count(file($filePath)) - 1);
        $bar = $this->output->createProgressBar($totalLines);
        $bar->start();

        $count = 0;
        $failed = [];

        foreach ($csv->readPayments($filePath, $uf) as $row) {
            try {
                $expenseExternalId = (int) ($row['SQ_DESPESA'] ?? 0);

                if ($expenseExternalId <= 0) {
                    $bar->advance();
                    continue;
                }

                $expense = CandidateExpense::where(
                    'expense_external_id',
                    $expenseExternalId
                )->first();

                if (!$expense) {
                    $failed[] = $expenseExternalId;

                    $bar->clear();
                    $this->warn(
                        "Despesa contratada não encontrada: {$expenseExternalId}"
                    );
                    $bar->display();

                    $bar->advance();
                    continue;
                }

                CandidateExpensePayment::updateOrCreate(
                    [
                        'expense_external_id' => $expenseExternalId,
                        'installment_external_id' =>
                            isset($row['SQ_PARCELAMENTO_DESPESA'])
                                ? (int) $row['SQ_PARCELAMENTO_DESPESA']
                                : null,
                    ],
                    [
                        'candidate_expense_id' => $expense->id,
                        'payment_date' => $csv->parseBrazilianDate($row['DT_PAGTO_DESPESA'] ?? null),
                        'paid_amount' => $csv->parseBrazilianDecimal($row['VR_PAGTO_DESPESA'] ?? null),
                        'resource_type' => $row['DS_ESPECIE_RECURSO'] ?? null,
                        'document_type' => $row['DS_TIPO_DOCUMENTO'] ?? null,
                        'document_number' => $row['NR_DOCUMENTO'] ?? null,
                        'expense_source' => $row['DS_FONTE_DESPESA'] ?? null,
                        'expense_origin' => $row['DS_ORIGEM_DESPESA'] ?? null,
                        'expense_nature' => $row['DS_NATUREZA_DESPESA'] ?? null,
                        'raw_data' => $row,
                    ]
                );

                $count++;
            } catch (\Throwable $e) {
                $failed[] = $row['SQ_DESPESA'] ?? 'desconhecida';

                $bar->clear();

                $this->error(
                    "Falha ao importar pagamento " .
                    ($row['SQ_DESPESA'] ?? '') .
                    ": {$e->getMessage()}"
                );

                $bar->display();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(
            "Importação concluída: {$count} pagamento(s) processado(s)."
        );

        if (!empty($failed)) {
            $this->warn(
                count($failed) .
                ' falharam: ' .
                implode(', ', $failed)
            );
        }

        return self::SUCCESS;
    }
}