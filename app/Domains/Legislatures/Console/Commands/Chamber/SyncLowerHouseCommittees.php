<?php

namespace App\Domains\Legislatures\Console\Commands\Chamber;

use App\Domains\Legislatures\Models\Committee;
use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Services\Chamber\LowerHouseApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncLowerHouseCommittees extends Command
{
    protected $signature = 'sync:committees-lower-house';
    protected $description = 'Fetch and store current committee memberships for already-synced CE deputies';

    public function handle(LowerHouseApiService $api)
    {
        $legislators = Legislator::where('chamber', 'lower_house')->where('state', 'CE')->get();
        $this->info('Syncing committees for ' . $legislators->count() . ' deputies.');
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            try {
                $orgaos = $api->getCommittees($legislator->external_id);

                $ativos = collect($orgaos)->filter(fn ($o) => empty($o['dataFim']));

                foreach ($ativos as $o) {
                    $committee = Committee::updateOrCreate(
                        ['external_id' => (string) $o['idOrgao'], 'chamber' => 'lower_house'],
                        [
                            'name' => $o['nomeOrgao'] ?? null,
                            'acronym' => $o['siglaOrgao'] ?? null,
                        ]
                    );

                    DB::table('committee_legislator')->updateOrInsert(
                        ['legislator_id' => $legislator->id, 'committee_id' => $committee->id],
                        [
                            'role' => $o['titulo'] ?? null,
                            'start_date' => $o['dataInicio'] ?? null,
                            'end_date' => $o['dataFim'] ?? null,
                            'updated_at' => now(),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                $this->error("Failed to sync committees for legislator {$legislator->external_id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Committee sync completed.');
    }
}