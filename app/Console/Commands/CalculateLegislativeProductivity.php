<?php

namespace App\Console\Commands;

use App\Models\Legislator;
use App\Services\Metrics\LegislativeProductivityService;
use Illuminate\Console\Command;

class CalculateLegislativeProductivity extends Command
{
    protected $signature = 'metrics:productivity';
    protected $description = 'Calcula e persiste a métrica de produtividade por mandato';

    public function handle(LegislativeProductivityService $service)
    {
        $legislators = Legislator::all();
        $this->info("Calculando produtividade para {$legislators->count()} parlamentares.");
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            try {
                $result = $service->calculate($legislator);

                $legislator->update([
                    'mandate_started_at' => $result['mandate_started_at'],
                    'productivity_bills_per_year' => $result['bills_per_year'],
                    'productivity_calculated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $this->error("Falha ao calcular produtividade para {$legislator->external_id}: " . $e->getMessage());
            }

            usleep(150_000);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Cálculo concluído.');
    }
}