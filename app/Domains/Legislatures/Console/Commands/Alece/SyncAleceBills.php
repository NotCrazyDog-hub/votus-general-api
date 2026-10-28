<?php

namespace App\Domains\Legislatures\Console\Commands\Alece;

use App\Domains\Legislatures\Models\Bill;
use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Services\Alece\AleceLegislativeService;
use Illuminate\Console\Command;

class SyncAleceBills extends Command
{
    protected $signature = 'sync:bills-alece';
    protected $description = 'Busca e armazena proposições (PL/PEC) de deputados estaduais do Ceará via site da Alece';

    public function handle(AleceLegislativeService $service)
    {
        $legislators = Legislator::where('chamber', 'state_house')
            ->where('state', 'CE')
            ->get();

        $this->info("Sincronizando proposições de {$legislators->count()} deputado(s) estadual(is).");
        $bar = $this->output->createProgressBar($legislators->count());

        $stats = [
            'bills_found' => 0,
            'bills_new_or_updated' => 0,
        ];

        foreach ($legislators as $legislator) {
            try {
                $bills = $service->getBillsByAuthorName($legislator->parliamentary_name);
                $stats['bills_found'] += count($bills);

                foreach ($bills as $row) {
                    $detail = $service->getBillDetail($row['detail_url']);

                    $bill = Bill::updateOrCreate(
                        [
                            'external_id' => "{$row['legislature']}_{$row['codigo']}",
                            'chamber' => 'state_house',
                        ],
                        [
                            'type' => $row['type'],
                            'summary' => $detail['summary'] ?? $row['summary_short'],
                            'presented_at' => $detail['presented_at'],
                            'status_situacao' => $detail['status_description'],
                            'status_sigla' => $row['status_short'],
                            'status_tramitando' => $row['status_tramitando'],
                            'status_checked_at' => now(),
                            'raw_data' => array_merge($row, $detail),
                        ]
                    );

                    $wasExisting = $bill->wasRecentlyCreated === false;
                    $stats['bills_new_or_updated']++;

                    $bill->legislators()->syncWithoutDetaching([$legislator->id]);

                    // Classificação automática de temas Votus (não interrompe o fluxo em caso de falha)
                    try {
                        

                        usleep(150_000); // não martela o servidor a cada detalhe
                    } catch (\Throwable $e) {
                        usleep(150_000); // não martela o servidor a cada detalhe
                        $this->newLine();
                        $this->error("Falha na classificação de {$bill->external_id}: " . $e->getMessage());
                    }

                    usleep(150_000); // não martela o servidor a cada detalhe
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Falha ao sincronizar proposições de {$legislator->parliamentary_name}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('Sincronização de proposições da Alece concluída.');
        $this->info(sprintf(
            "Proposições encontradas: %d | Novas/atualizadas: %d | Classificações realizadas: %d | Ignoradas (já atualizadas): %d | Reclassificações: %d | Falhas de classificação: %d",
            $stats['bills_found'],
            $stats['bills_new_or_updated'],
            $stats['classifications_done'],
            $stats['classifications_skipped'],
            $stats['reclassifications'],
            $stats['classifications_failed']
        ));
    }
}