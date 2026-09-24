<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Models\CandidacyHistory;
use App\Services\TseCandidacyHistoryCsvService;
use Illuminate\Console\Command;

class ImportCandidacyHistory extends Command
{
    //comando:
    //php artisan import:candidacy-history storage/app/tse/historico_candidatura_2026_CE.csv --uf=CE --year=2026
    protected $signature = 'import:candidacy-history
        {file : Caminho do arquivo historico_candidatura CSV}
        {--uf=CE : SG_UF_ATUAL a filtrar}
        {--year=2026 : ANO_ELEICAO_ATUAL a filtrar}';

    protected $description = 'Importa o histórico de candidaturas anteriores, vinculando ao candidato atual pelo SQ_CANDIDATO_ATUAL';

    public function handle(TseCandidacyHistoryCsvService $csv)
    {
        $filePath = $this->argument('file');
        $uf = $this->option('uf');
        $year = (int) $this->option('year');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Importando histórico de candidaturas ({$uf}/{$year})...");

        $totalLines = max(0, count(file($filePath)) - 1);

        $bar = $this->output->createProgressBar($totalLines);
        $bar->start();

        $imported = 0;
        $skipped = [];

        foreach ($csv->readHistory($filePath, $uf, $year) as $row) {
            $bar->advance();

            $currentExternalId = $row['SQ_CANDIDATO_ATUAL'] ?? null;

            $candidate = Candidate::where('external_id', $currentExternalId)->first();

            if (!$candidate) {
                $skipped[] = "SQ_CANDIDATO_ATUAL {$currentExternalId} não encontrado na tabela candidates";
                continue;
            }

            try {
                CandidacyHistory::updateOrCreate(
                    [
                        'candidacy_external_id' => (int) $row['SQ_CANDIDATO'],
                        'round' => (int) $row['NR_TURNO'],
                    ],
                    [
                        'candidate_id' => $candidate->id,
                        'election_year' => (int) $row['ANO_ELEICAO'],
                        'state' => $row['SG_UF'] ?? null,
                        'office_name' => $row['DS_CARGO'] ?? null,
                        'ballot_number' => $row['NR_CANDIDATO'] ?? null,
                        'party_acronym' => $row['SG_PARTIDO'] ?? null,
                        'party_name' => $row['NM_PARTIDO'] ?? null,
                        'candidacy_status' => $row['DS_SITUACAO_CANDIDATURA'] ?? null,
                        'result_status' => $row['DS_SIT_TOT_TURNO'] ?? null,
                        'raw_data' => $row,
                    ]
                );

                $imported++;
            } catch (\Throwable $e) {
                $skipped[] = "SQ_CANDIDATO {$row['SQ_CANDIDATO']}: {$e->getMessage()}";

                $bar->clear();
                $this->error(
                    "Falha ao importar candidato " .
                    ($row['SQ_CANDIDATO'] ?? '') .
                    ": {$e->getMessage()}"
                );
                $bar->display();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("{$imported} registro(s) de histórico importado(s).");

        if (!empty($skipped)) {
            $this->warn(count($skipped) . ' linha(s) não importada(s):');

            foreach (array_slice($skipped, 0, 20) as $item) {
                $this->line("  - {$item}");
            }

            if (count($skipped) > 20) {
                $this->line(
                    '  ... e mais ' . (count($skipped) - 20) . ' linha(s)'
                );
            }
        }

        return self::SUCCESS;
    }
}