<?php

namespace App\Domains\Legislatures\Console\Commands\Alece;

use App\Domains\Legislatures\Services\Alece\AleceLegislatorService;
use Illuminate\Console\Command;
use Throwable;

class SyncAleceLegislators extends Command
{
    protected $signature = 'sync:alece-legislators';

    protected $description = 'Importa e atualiza os parlamentares da ALECE';

    public function __construct(
        protected AleceLegislatorService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando sincronização dos parlamentares da ALECE...');
        $this->newLine();

        $progressBar = null;
        $errors = 0;

        try {
            $count = $this->service->sync(
                function (
                    int $current,
                    int $total,
                    int $processed,
                    string $url,
                    ?string $error
                ) use (&$progressBar, &$errors) {
                    if ($progressBar === null) {
                        $progressBar = $this->output->createProgressBar($total);

                        $progressBar->setFormat(
                            ' %current%/%max% [%bar%] %percent:3s%%'
                        );

                        $progressBar->start();
                    }

                    $progressBar->setProgress($current);

                    if ($error) {
                        $errors++;

                        $this->newLine();

                        $this->warn(
                            "Erro em {$url}: {$error}"
                        );
                    }
                }
            );

            if ($progressBar) {
                $progressBar->finish();
                $this->newLine(2);
            }

            $this->info(
                "Sincronização concluída. {$count} parlamentar(es) processado(s)."
            );

            if ($errors > 0) {
                $this->warn(
                    "{$errors} parlamentar(es) apresentaram erro."
                );
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            if ($progressBar) {
                $this->newLine();
            }

            $this->error('Erro durante a sincronização:');
            $this->error($e->getMessage());

            report($e);

            return self::FAILURE;
        }
    }
}