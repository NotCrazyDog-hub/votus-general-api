<?php

namespace App\Domains\Legislatures\Console\Commands\Metrics;

use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Services\Metrics\ThematicFocusService;
use Illuminate\Console\Command;

class CalculateThematicFocus extends Command
{
    protected $signature = 'metrics:thematic-focus';
    protected $description = 'Calcula e persiste o índice de foco temático (especialista vs. generalista) por parlamentar';

    public function handle(ThematicFocusService $service)
    {
        $legislators = Legislator::all();
        $this->info("Calculando foco temático para {$legislators->count()} parlamentares.");
        $bar = $this->output->createProgressBar($legislators->count());

        foreach ($legislators as $legislator) {
            $result = $service->calculate($legislator);

            $legislator->update([
                'thematic_focus_total_bills' => $result['total_bills'],
                'thematic_focus_index' => $result['index'],
                'thematic_focus_top_topic_id' => $result['top_topic_id'],
                'thematic_focus_top_topic_share' => $result['top_topic_share'],
                'thematic_focus_calculated_at' => now(),
            ]);

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Cálculo concluído.');
    }
}