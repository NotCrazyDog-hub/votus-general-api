<?php

namespace App\Console\Commands;

use App\Models\Candidate;
use App\Services\TseCandidatesCsvService;
use Illuminate\Console\Command;

class SyncCandidatesTse extends Command
{
    // Comando:
    // php artisan sync:candidates-tse storage/app/tse/consulta_cand_2026_CE.csv storage/app/tse/consulta_cand_complementar_2026_CE.csv --uf=CE --year=2026

    protected $signature = 'sync:candidates-tse
        {file : Caminho do arquivo CSV de candidatos}
        {complementary_file : Caminho do CSV de informações complementares}
        {--uf=CE : Sigla da UF a importar}
        {--year=2026 : Ano da eleição}';

    protected $description = 'Importa candidatos a partir dos CSVs de candidaturas e informações complementares do TSE';

    protected array $offices = [
        'PRESIDENTE',
        'VICE-PRESIDENTE',
        'GOVERNADOR',
        'VICE-GOVERNADOR',
        'SENADOR',
        '1º SUPLENTE',
        '2º SUPLENTE',
        'DEPUTADO FEDERAL',
        'DEPUTADO ESTADUAL',
    ];

    public function handle(TseCandidatesCsvService $csv)
    {
        $filePath = $this->argument('file');
        $complementaryFilePath = $this->argument('complementary_file');

        $uf = $this->option('uf');
        $year = (int) $this->option('year');

        // Verifica arquivo principal
        if (!file_exists($filePath)) {
            $this->error("Arquivo não encontrado: {$filePath}");
            return self::FAILURE;
        }

        // Verifica arquivo complementar
        if (!file_exists($complementaryFilePath)) {
            $this->error(
                "Arquivo complementar não encontrado: {$complementaryFilePath}"
            );

            return self::FAILURE;
        }

        $this->info("Importando candidatos de {$uf} ({$year})...");

        // Conta as linhas do arquivo principal, ignorando o cabeçalho
        $totalLines = max(0, count(file($filePath)) - 1);

        $bar = $this->output->createProgressBar($totalLines);
        $bar->start();

        $count = 0;
        $failed = [];

        foreach (
            $csv->readCandidates(
                $filePath,
                $complementaryFilePath,
                $uf,
                $this->offices
            ) as $row
        ) {
            try {
                Candidate::updateOrCreate(
                    [
                        'external_id' => (int) $row['SQ_CANDIDATO'],
                        'round' => (int) $row['NR_TURNO'],
                    ],
                    [
                        'ballot_number' => $row['NR_CANDIDATO'] ?? null,
                        'coverage_scope' => $row['TP_ABRANGENCIA'] ?? null,
                        'state' => $row['SG_UF'] ?? null,
                        'office_code' => 
                            isset($row['CD_CARGO'])
                                ? (int) $row['CD_CARGO']
                                : null,
                        'office_name' => $row['DS_CARGO'] ?? null,
                        'civil_name' => $row['NM_CANDIDATO'] ?? null,
                        'ballot_name' => $row['NM_URNA_CANDIDATO'] ?? null,
                        'cpf' => $row['NR_CPF_CANDIDATO'] ?? null,
                        'party_acronym' => $row['SG_PARTIDO'] ?? null,
                        'party_name' => $row['NM_PARTIDO'] ?? null,
                        'education_level' => $row['DS_GRAU_INSTRUCAO'] ?? null,
                        'occupation' => $row['DS_OCUPACAO'] ?? null,
                        'race_color' => $row['DS_COR_RACA'] ?? null,
                        'judgment_status_code' =>
                            isset($row['CD_SITUACAO_JULGAMENTO'])
                                ? (int) $row['CD_SITUACAO_JULGAMENTO']
                                : null,
                        'judgment_status' =>$row['DS_SITUACAO_JULGAMENTO'] ?? null,
                        'election_year' => $year,
                        'raw_data' => $row,
                    ]
                );

                $count++;
            } catch (\Throwable $e) {
                $failed[] =
                    $row['SQ_CANDIDATO'] ?? 'desconhecido';

                // Limpa a barra temporariamente para exibir o erro
                $bar->clear();

                $this->error(
                    "Falha ao importar candidato " .
                    ($row['SQ_CANDIDATO'] ?? '') .
                    ": {$e->getMessage()}"
                );

                $bar->display();
            }

            $bar->advance();
        }

        $bar->finish();

        $this->newLine(2);

        $this->info(
            "Importação concluída: {$count} candidato(s) processado(s)."
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