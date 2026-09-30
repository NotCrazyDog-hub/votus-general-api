<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Legislator;
use App\Services\AleceLegislativeService;
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

        $this->info("Sincronizando proposições de {$legislators->count()} deputado(s) estaduais.");
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            try {
                $bills = $service->getBillsByAuthorName($legislator->parliamentary_name);

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

                    $bill->legislators()->syncWithoutDetaching([$legislator->id]);

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
    }
}