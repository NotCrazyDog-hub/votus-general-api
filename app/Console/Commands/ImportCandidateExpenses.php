<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Models\CandidateExpense;
use App\Services\TseCandidateExpensesCsvService;
use Illuminate\Console\Command;

class ImportCandidateExpenses extends Command
{
    protected $signature = 'import:candidate-expenses
        {file : Path to the candidate expenses CSV file}';

    protected $description = 'Import candidate expenses and link them by SQ_CANDIDATO';

    public function handle(TseCandidateExpensesCsvService $csv)
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info('Importing candidate expenses...');

        // Count CSV lines, excluding the header
        $totalLines = max(0, count(file($filePath)) - 1);

        $bar = $this->output->createProgressBar($totalLines);
        $bar->start();

        $imported = 0;
        $skipped = [];

        foreach ($csv->readExpenses($filePath) as $row) {
            if (
                empty($row['DT_DESPESA']) ||
                ($row['SQ_DESPESA'] ?? '-1') === '-1'
            ) {
                $bar->advance();
                continue;
            }

            $externalId = $row['SQ_CANDIDATO'] ?? null;

            $candidate = Candidate::where('external_id', $externalId)->first();

            if (!$candidate) {
                $skipped[] = "SQ_CANDIDATO {$externalId} not found (outside system scope)";

                $bar->advance();
                continue;
            }

            try {
                CandidateExpense::updateOrCreate(
                    ['expense_external_id' => (int) $row['SQ_DESPESA']],
                    [
                        'candidate_id' => $candidate->id,
                        'expense_date' => $csv->parseBrazilianDate($row['DT_DESPESA'] ?? null),
                        'description' => $row['DS_DESPESA'] ?? '',
                        'amount' => $csv->parseBrazilianDecimal($row['VR_DESPESA_CONTRATADA'] ?? null),
                        'supplier_document' => $row['NR_CPF_CNPJ_FORNECEDOR'] ?? null,
                        'supplier_name' => $row['NM_FORNECEDOR'] ?? null,
                        'supplier_type' => $row['DS_TIPO_FORNECEDOR'] ?? null,
                        'document_type' => $row['DS_TIPO_DOCUMENTO'] ?? null,
                        'document_number' => $row['NR_DOCUMENTO'] ?? null,
                        'expense_origin' => $row['DS_ORIGEM_DESPESA'] ?? null,
                        'raw_data' => $row,
                    ]
                );

                $imported++;
            } catch (\Throwable $e) {
                $skipped[] = "SQ_DESPESA {$row['SQ_DESPESA']}: {$e->getMessage()}";

                $bar->clear();
                $this->error(
                    "Failed to import expense {$row['SQ_DESPESA']}: {$e->getMessage()}"
                );
                $bar->display();
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Import completed: {$imported} expense(s) processed.");

        if (!empty($skipped)) {
            $this->warn(
                count($skipped) . ' row(s) skipped:'
            );

            foreach (array_slice($skipped, 0, 10) as $item) {
                $this->line("  - {$item}");
            }

            if (count($skipped) > 10) {
                $this->line(
                    '  ... and ' . (count($skipped) - 10) . ' more row(s)'
                );
            }
        }

        return self::SUCCESS;
    }
}