<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Services\TseCandidatesCsvService;
use Illuminate\Console\Command;

class SyncCandidatesTse extends Command
{
    protected $signature = 'sync:candidates-tse
        {file : Caminho do arquivo CSV do TSE (ex: consulta_cand_2026_CE.csv)}
        {--uf=CE : Sigla da UF a importar}
        {--year=2026 : Ano da eleição}';

    protected $description = 'Importa candidatos (governador, senador, dep. federal, dep. estadual) a partir do CSV de candidaturas do TSE';

    protected array $offices = ['GOVERNADOR', 'SENADOR', 'DEPUTADO FEDERAL', 'DEPUTADO ESTADUAL'];

    public function handle(TseCandidatesCsvService $csv)
    {
        $filePath = $this->argument('file');
        $uf = $this->option('uf');
        $year = (int) $this->option('year');

        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return self::FAILURE;
        }

        $this->info("Importando candidatos de {$uf} ({$year})...");

        // Exemplo simples contando as linhas do arquivo (menos o cabeçalho)
        $totalLines = max(0, count(file($filePath)) - 1);

        $bar = $this->output->createProgressBar($totalLines);
        $bar->start();

        $count = 0;
        $failed = [];

        foreach ($csv->readCandidates($filePath, $uf, $this->offices) as $row) {
            try {
                Candidate::updateOrCreate(
                    [
                        'external_id' => (int) $row['SQ_CANDIDATO'],
                        'round' => (int) $row['NR_TURNO'],
                    ],
                    [
                        'ballot_number' => $row['NR_CANDIDATO'] ?? null,
                        'coverage_scope' => $row['TP_ABRANGENCIA'] ?? null,
                        'state' => $row['SG_UF'],
                        'office_code' => isset($row['CD_CARGO']) ? (int) $row['CD_CARGO'] : null,
                        'office_name' => $row['DS_CARGO'],
                        'civil_name' => $row['NM_CANDIDATO'],
                        'ballot_name' => $row['NM_URNA_CANDIDATO'],
                        'cpf' => $row['NR_CPF_CANDIDATO'] ?? null,
                        'party_acronym' => $row['SG_PARTIDO'] ?? null,
                        'party_name' => $row['NM_PARTIDO'] ?? null,
                        'education_level' => $row['DS_GRAU_INSTRUCAO'] ?? null,
                        'occupation' => $row['DS_OCUPACAO'] ?? null,
                        'race_color' => $row['DS_COR_RACA'] ?? null,
                        'election_year' => $year,
                        'raw_data' => $row,
                    ]
                );
                $count++;
            } catch (\Throwable $e) {
                $failed[] = $row['SQ_CANDIDATO'] ?? 'desconhecido';
                
                // Limpa a barra temporariamente para não quebrar o layout ao exibir erro
                $bar->clear();
                $this->error("Falha ao importar candidato " . ($row['SQ_CANDIDATO'] ?? '') . ": {$e->getMessage()}");
                $bar->display();
            }

            // Avança a barra a cada candidato processado
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Importação concluída: {$count} candidato(s) processado(s).");

        if (!empty($failed)) {
            $this->warn(count($failed) . ' falharam: ' . implode(', ', $failed));
        }

        return self::SUCCESS;
    }
}