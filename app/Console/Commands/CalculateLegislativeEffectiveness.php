<?php

namespace App\Console\Commands;

use App\Models\Legislator;
use App\Services\Metrics\LegislativeEffectivenessService;
use Illuminate\Console\Command;

class CalculateLegislativeEffectiveness extends Command
{
    protected $signature = 'metrics:effectiveness';
    protected $description = 'Calcula e persiste a métrica de efetividade legislativa por parlamentar';

    public function handle(LegislativeEffectivenessService $service)
    {
        $legislators = Legislator::all();
        $this->info("Calculando efetividade para {$legislators->count()} parlamentares.");
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            $result = $service->calculate($legislator);

            $legislator->update([
                'effectiveness_total_bills' => $result['total'],
                'effectiveness_advanced_bills' => $result['advanced'],
                'effectiveness_rate' => $result['rate'],
                'effectiveness_wilson_lower' => $result['wilson_lower'],
                'effectiveness_calculated_at' => now(),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Cálculo concluído.');
    }
}