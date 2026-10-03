<?php

namespace App\Domains\Legislatures\Console\Commands\Chamber;

use App\Domains\Legislatures\Models\Bill;
use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Services\Chamber\LowerHouseApiService;
use Illuminate\Console\Command;

class SyncLowerHouseBills extends Command
{
    protected $signature = 'sync:bills-lower-house';
    protected $description = 'Fetch and store bills authored by CE deputies';

    public function handle(LowerHouseApiService $api)
    {
        $legislators = Legislator::where('chamber', 'lower_house')->where('state', 'CE')->get();
        $this->info('Syncing bills for ' . $legislators->count() . ' deputies.');
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            try {
                $bills = $api->getBillsByLegislator($legislator->external_id);

                foreach ($bills as $b) {
                    $bill = Bill::updateOrCreate(
                        ['external_id' => (string) $b['id'], 'chamber' => 'lower_house'],
                        [
                            'type' => $b['siglaTipo'] ?? null,
                            'summary' => $b['ementa'] ?? null,
                            'presented_at' => $b['dataApresentacao'] ?? null,
                            'raw_data' => $b,
                        ]
                    );

                    $bill->legislators()->syncWithoutDetaching([$legislator->id]);
                }
            } catch (\Throwable $e) {
                $this->error("Failed to sync bills for legislator {$legislator->external_id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Bill sync completed.');
    }
}