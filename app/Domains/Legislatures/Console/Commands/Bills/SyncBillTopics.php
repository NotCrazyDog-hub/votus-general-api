<?php

namespace App\Domains\Legislatures\Console\Commands\Bills;

use App\Domains\Legislatures\Models\Bill;
use App\Domains\Legislatures\Models\Topic;
use App\Domains\Legislatures\Services\Chamber\LowerHouseApiService;
use App\Domains\Legislatures\Services\Senate\SenateApiService;
use Illuminate\Console\Command;

class SyncBillTopics extends Command
{
    protected $signature = 'sync:bill-topics {--chamber= : Chamber to sync (senate or lower_house)}';
    protected $description = 'Fetch and store topics/themes for bills already registered in the database';

    /**
     * Sincroniza os temas das proposições da Câmara ou do Senado, conforme a câmara selecionada.
     */

    public function handle(LowerHouseApiService $lowerHouseApi, SenateApiService $senateApi)
    {
        $chamber = $this->option('chamber');

        if ($chamber === 'lower_house') {
            $this->syncLowerHouse($lowerHouseApi);
        } elseif ($chamber === 'senate') {
            $this->syncSenate($senateApi);
        } else {
            $this->error('Invalid chamber. Use: senate or lower_house');
            return self::FAILURE;
        }

        $this->info('Bill topics sync completed.');

        return self::SUCCESS;
    }


    /** Busca temas de cada proposição da Câmara e continua mesmo se uma chamada individual falhar. */
    protected function syncLowerHouse(LowerHouseApiService $api): void
    {
        $bills = Bill::where('chamber', 'lower_house')->get();
        $this->info("Syncing topics for {$bills->count()} lower house bills.");
        $bar = $this->output->createProgressBar($bills->count());

        foreach ($bills as $bill) {
            try {
                $topics = $api->getTopics($bill->external_id);
                $this->attachTopics($bill, $topics, 'lower_house');
            } catch (\Throwable $e) {
                $this->error("Failed to sync topics for bill {$bill->external_id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /** Busca temas de cada proposição do Senado com pausa entre chamadas para respeitar a API. */
    protected function syncSenate(SenateApiService $api): void
    {
        $bills = Bill::where('chamber', 'senate')->get();
        $this->info("Syncing topics for {$bills->count()} senate bills.");
        $bar = $this->output->createProgressBar($bills->count());

        foreach ($bills as $bill) {
            try {
                $topics = $api->getTopics($bill->external_id);
                $this->attachTopics($bill, $topics, 'senate');
            } catch (\Throwable $e) {
                $this->error("Failed to sync topics for bill {$bill->external_id}: " . $e->getMessage());
            }

            usleep(300_000); // chamada nova por bill, evita martelar a API
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    /** Cria ou reutiliza o tema da casa e associa-o sem remover temas já vinculados à proposição. */
    protected function attachTopics(Bill $bill, array $topics, string $chamber): void
    {
        foreach ($topics as $t) {
            $topic = Topic::firstOrCreate(
                ['name' => $t['name'], 'chamber' => $chamber],
                ['external_id' => $t['external_id'] ?? null]
            );

            $bill->topics()->syncWithoutDetaching([
                $topic->id => ['relevance' => $t['relevance'] ?? null],
            ]);
        }
    }
}