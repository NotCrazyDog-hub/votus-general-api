<?php

namespace App\Domains\GovernmentPlans\Console\Commands;

use App\Domains\GovernmentPlans\Services\PresidentialProposalImportService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Regenera os resumos comparativos (IA) por tema a partir das propostas que já
 * estão no banco. Não busca sites, não extrai propostas, não altera propostas
 * e não apaga nada: as comparações são atualizadas no lugar.
 */
class ComparePresidentialPlans extends Command
{
    protected $signature = 'compare:presidential-plans
        {--candidate=* : external_id dos candidatos; sem opção usa os configurados}
        {--force : confirma a escrita quando APP_ENV=production}';

    protected $description = 'Regenera os resumos comparativos por tema a partir das propostas já gravadas';

    /**
     * Gera ou atualiza comparações entre propostas de candidatos usando os dados já importados.
     */

    public function handle(PresidentialProposalImportService $service): int
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Execução bloqueada em produção. Use --force para confirmar.');

            return self::FAILURE;
        }

        try {
            $result = $service->compareStored($this->option('candidate'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('%d comparações geradas/atualizadas para %d candidatos.', $result['comparisons'], $result['candidates']));

        return self::SUCCESS;
    }
}
