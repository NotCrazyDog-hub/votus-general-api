<?php

namespace App\Domains\Legislatures\Console\Commands\Senate;

use App\Domains\Legislatures\Models\Bill;
use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Services\Senate\SenateApiService;
use Illuminate\Console\Command;

class SyncSenateBills extends Command
{
    protected $signature = 'sync:bills-senate';
    protected $description = 'Fetch and store PL/PEC bills authored by CE senators';

    public function handle(SenateApiService $api)
    {
        $legislators = Legislator::where('chamber', 'senate')->where('state', 'CE')->get();
        $this->info('Syncing bills for ' . $legislators->count() . ' senators.');
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            try {
                $bills = $api->getBillsByLegislator($legislator->external_id);

                foreach ($bills as $b) {
                    $type = explode(' ', $b['identificacao'] ?? '')[0] ?? null;

                    $bill = Bill::updateOrCreate(
                        ['external_id' => (string) $b['id'], 'chamber' => 'senate'],
                        [
                            'type' => $type,
                            'summary' => $b['ementa'] ?? null,
                            'presented_at' => $b['dataApresentacao'] ?? null,
                            'raw_data' => $b,
                        ]
                    );

                    $bill->legislators()->syncWithoutDetaching([$legislator->id]);
                }
            } catch (\Throwable $e) {
                $this->error("Failed to sync bills for senator {$legislator->external_id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Bill sync completed.');
    }
}