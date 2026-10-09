<?php

namespace App\Console\Commands;

use App\Enums\CandidateOffice;
use App\Services\Tse\CandidateSyncService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sincroniza candidatos a partir da API DivulgaCandContas do TSE.
 *
 * Substitui o fluxo dos CSVs de Dados Abertos (era
 * `sync:candidates-tse arquivo.csv arquivo_complementar.csv`). O nome do
 * comando foi mantido de propósito — a rotina operacional continua a mesma,
 * mudou só a fonte dos dados.
 */
class SyncCandidatesTse extends Command
{
    // Comando:
    // php artisan sync:candidates-tse --uf=CE --year=2026 --office=state_deputy

    protected $signature = 'sync:candidates-tse
        {--uf=CE : Sigla da UF a sincronizar}
        {--year=2026 : Ano da eleição}
        {--office=governor : Cargo (president|governor|senator|federal_deputy|state_deputy)}
        {--election-id= : ID da eleição. Padrão: derivado do ano (2026 => 20322002026)}
        {--disk=supabase : Disco de armazenamento de fotos e documentos}
        {--without-files : Não baixa fotos nem planos de governo}';

    protected $description = 'Sincroniza candidatos do TSE via API DivulgaCandContas (substitui os CSVs de Dados Abertos)';

    /**
     * IDs de eleição do TSE — 2026 é 20322002026.
     *
     * @var array<int, string>
     */
    protected array $electionIds = [
        2026 => '20322002026',
    ];

    public function handle(CandidateSyncService $sync): int
    {
        $year = (int) $this->option('year');
        $uf = strtoupper((string) $this->option('uf'));
        $office = CandidateOffice::tryFrom((string) $this->option('office'));

        if ($office === null) {
            $this->error('Cargo inválido. Use: '.implode('|', array_column(CandidateOffice::cases(), 'value')));

            return self::FAILURE;
        }

        $electionId = (string) ($this->option('election-id') ?: ($this->electionIds[$year] ?? ''));

        if ($electionId === '') {
            $this->error("Não há electionId conhecido para {$year}. Use --election-id.");

            return self::FAILURE;
        }

        $this->info("Sincronizando candidatos de {$uf}/{$year} — {$office->value} (electionId {$electionId})...");

        try {
            $summary = $sync->syncOffice(
                year: $year,
                uf: $uf,
                electionId: $electionId,
                office: $office,
                disk: (string) $this->option('disk'),
                withFiles: ! $this->option('without-files'),
                onProgress: function () {
                    $this->output->write('.');
                },
            );
        } catch (Throwable $e) {
            // Falha de rede ou 5xx na listagem não tem candidato individual
            // para reportar — é a chamada inteira que caiu.
            $this->newLine(2);
            $this->error("Falha ao sincronizar candidatos: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->newLine(2);

        $this->info("Sincronização concluída: {$summary['persisted']} candidato(s), {$summary['histories']} histórico(s).");
        $this->line("Fotos: {$summary['photos']} | Planos de governo: {$summary['documents']} | Vínculos de vice: {$summary['running_mates']}");

        if ($summary['failed'] !== []) {
            $this->warn(count($summary['failed']).' candidato(s) falharam:');

            foreach (array_slice($summary['failed'], 0, 20) as $item) {
                $this->line("  - {$item}");
            }

            if (count($summary['failed']) > 20) {
                $this->line('  ... e mais '.(count($summary['failed']) - 20).' candidato(s)');
            }
        }

        return self::SUCCESS;
    }
}
