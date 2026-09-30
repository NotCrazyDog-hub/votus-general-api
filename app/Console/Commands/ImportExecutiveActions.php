<?php

namespace App\Console\Commands;

use App\Models\Executive;
use App\Models\ExecutiveAction;
use App\Services\ExecutiveActionImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ImportExecutiveActions extends Command
{
    protected $signature = 'import:executive-actions
                            {file : Caminho do arquivo CSV}
                            {--executive= : ID do executivo para todas as linhas}';

    protected $description = 'Importa ações de executivos a partir de um CSV';

    public function handle(
        ExecutiveActionImportService $importService
    ): int {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("Arquivo não encontrado: {$file}");

            return self::FAILURE;
        }

        try {
            $rows = $importService->read($file);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'Encontradas ' . count($rows) . ' linhas no CSV.'
        );

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            // Permite informar o executivo pelo command:
            // --executive=1
            if ($this->option('executive')) {
                $row['executive_id'] = (int) $this->option('executive');
            }

            $validator = Validator::make($row, [
                'executive_id' => [
                    'required',
                    'integer',
                    'exists:executives,id',
                ],

                'title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'source_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'source_url' => [
                    'required',
                    'url',
                ],

                'relevance_score' => [
                    'nullable',
                    'numeric',
                    'between:0,100',
                ],
            ]);

            if ($validator->fails()) {
                $skipped++;

                $this->warn(
                    "Linha {$line} ignorada: " .
                    implode(
                        ' | ',
                        $validator->errors()->all()
                    )
                );

                continue;
            }

            $existing = ExecutiveAction::where(
                'executive_id',
                $row['executive_id']
            )
                ->where(
                    'source_url',
                    $row['source_url']
                )
                ->first();

            ExecutiveAction::updateOrCreate(
                [
                    'executive_id' => $row['executive_id'],
                    'source_url' => $row['source_url'],
                ],
                $row
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        $this->newLine();

        $this->info("Criadas: {$created}");
        $this->info("Atualizadas: {$updated}");
        $this->warn("Ignoradas: {$skipped}");

        return self::SUCCESS;
    }
}