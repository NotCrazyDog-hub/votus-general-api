<?php

namespace App\Console\Commands;

use App\Services\GovernmentPlans\PresidentialProposalImportService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportPresidentialGovernmentPlans extends Command
{
    protected $signature = 'import:presidential-government-plans
        {--candidate=* : SQ_CANDIDATO dos candidatos a importar; sem opção usa os dois configurados}
        {--force : Confirma a escrita quando APP_ENV=production}';

    protected $description = 'Coleta e compara propostas dos sites oficiais dos candidatos presidenciais com a Groq';

    public function handle(PresidentialProposalImportService $service): int
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Execução bloqueada em produção. Revise as fontes e execute novamente com --force.');

            return self::FAILURE;
        }

        try {
            $ids = $this->option('candidate');
            foreach ($ids as $id) {
                if (filter_var($id, FILTER_VALIDATE_INT) === false) {
                    throw new RuntimeException("ID de candidato inválido: {$id}");
                }
            }
            $result = $service->import($ids);
            $this->info(sprintf(
                'Importação concluída: %d candidatos, %d propostas, %d temas e %d comparações.',
                $result['candidates'], $result['proposals'], $result['themes'], $result['comparisons'],
            ));

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
