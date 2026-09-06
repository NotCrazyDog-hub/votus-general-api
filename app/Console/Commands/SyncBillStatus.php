<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Services\LowerHouseApiService;
use App\Services\SenateApiService;
use Illuminate\Console\Command;
use Carbon\Carbon;

class SyncBillStatus extends Command
{
    protected $signature = 'sync:bill-status
        {--chamber= : lower_house, senate, ou vazio para ambas}
        {--limit=500 : máximo de bills processados por câmara}
        {--force : reprocessa mesmo quem já tem status_checked_at recente}
        {--stale-days=15 : dias para considerar um status desatualizado}';

    protected $description = 'Syncs the current status of legislative proposals (legislative effectiveness)';

    public function handle(LowerHouseApiService $lowerHouseApi, SenateApiService $senateApi)
    {
        $chamber = $this->option('chamber');

        if (!$chamber || $chamber === 'lower_house') {
            $this->sync('lower_house', fn ($externalId) => $lowerHouseApi->getStatusAndHistory($externalId));
        }

        if (!$chamber || $chamber === 'senate') {
            $this->sync('senate', fn ($externalId) => $senateApi->getStatusAndHistory($externalId));
        }

        $this->info('Status sync completed.');
    }

    protected function sync(string $chamber, callable $fetch): void
    {
        $bills = $this->pendingQuery($chamber)->get();
        $label = $chamber === 'lower_house' ? 'Lower House' : 'Senate';
        $this->info("{$label}: syncing {$bills->count()} bills.");
        $bar = $this->output->createProgressBar($bills->count());

        foreach ($bills as $bill) {
            try {
                ['status' => $status, 'tramitations' => $tramitations] = $fetch($bill->external_id);

                $bill->update([
                    'status_situacao' => $status['situacao'] ?? null,
                    'status_sigla' => $status['sigla_situacao'] ?? $status['orgao'] ?? null,
                    'status_tramitando' => $status['tramitando'] ?? null,
                    'status_checked_at' => now(),
                ]);

                $bill->tramitations()->delete();
                $bill->tramitations()->createMany($tramitations);
            } catch (\Throwable $e) {
                $this->error("Failed to sync bill {$bill->external_id} ({$label}): " . $e->getMessage());
                $bill->update(['status_checked_at' => now()]);
            }

            usleep(250_000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
    }

    protected function pendingQuery(string $chamber)
    {
        $query = Bill::where('chamber', $chamber)->limit((int) $this->option('limit'));

        if ($this->option('force')) {
            return $query;
        }

        $staleDate = Carbon::now()->subDays((int) $this->option('stale-days'));

        return $query->where(function ($q) use ($staleDate) {
            $q->whereNull('status_checked_at')
              ->orWhere('status_checked_at', '<', $staleDate);
        });
    }
}