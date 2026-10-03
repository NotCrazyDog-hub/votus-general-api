<?php

namespace App\Domains\Legislatures\Console\Commands\Legislators;

use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Models\Profession;
use App\Domains\Legislatures\Services\Senate\SenateApiService;
use App\Domains\Legislatures\Services\Chamber\LowerHouseApiService;
use Illuminate\Console\Command;

class SyncLegislatorProfessions extends Command
{
    protected $signature = 'sync:legislator-professions';
    protected $description = 'Fetch declared professions for senators and deputies and save to database';

    public function handle(SenateApiService $senateApi, LowerHouseApiService $lowerHouseApi)
    {
        $legislators = Legislator::whereIn('chamber', ['senate', 'lower_house'])->get();
        $this->info("Syncing professions for {$legislators->count()} legislators.");
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            try {
                $entries = $legislator->chamber === 'senate'
                    ? $senateApi->getProfessions($legislator->external_id)
                    : $lowerHouseApi->getProfessions($legislator->external_id);

                foreach ($entries as $entry) {
                    if ($entry['camara_code'] !== null) {
                        $profession = Profession::firstOrCreate(
                            ['camara_code' => $entry['camara_code']],
                            ['normalized_name' => $entry['normalized_name']]
                        );
                    } else {
                        $profession = Profession::firstOrCreate(
                            ['normalized_name' => $entry['normalized_name']]
                        );
                    }

                    $legislator->professions()->syncWithoutDetaching([
                        $profession->id => [
                            'source' => $entry['source'],
                            'original_name' => $entry['original_name'],
                            'is_primary' => $entry['is_primary'],
                            'registered_at' => $entry['registered_at'],
                        ],
                    ]);
                }
            } catch (\Throwable $e) {
                $this->error("Failed to sync professions for legislator {$legislator->external_id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Profession sync completed.');
    }
}