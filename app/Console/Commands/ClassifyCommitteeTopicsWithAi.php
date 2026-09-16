<?php

namespace App\Console\Commands;

use App\Models\Committee;
use App\Models\Topic;
use App\Services\Metrics\CommitteeTopicAiClassificationService;
use Illuminate\Console\Command;

class ClassifyCommitteeTopicsWithAi extends Command
{
    protected $signature = 'metrics:classify-committee-topics
        {--ids= : IDs específicos de comissões, separados por vírgula (ex: 8,9,10)}
        {--from= : ID inicial de um range de comissões}
        {--to= : ID final de um range de comissões}';

    protected $description = 'Classifica via IA os pares comissão x tema e persiste os aprovados';

    public function handle(CommitteeTopicAiClassificationService $service)
    {
        $committeeIds = $this->resolveCommitteeIds();

        $committeeCount = $committeeIds !== null
            ? count($committeeIds)
            : Committee::count();

        $total = $committeeCount * Topic::count();

        $this->info($committeeIds !== null
            ? 'Classificando comissões: ' . implode(', ', $committeeIds)
            : 'Classificando todas as comissões.');
        $this->info("Até {$total} pares a processar.");

        $bar = $this->output->createProgressBar($total);

        $result = $service->classifyAll(fn () => $bar->advance(), $committeeIds);

        $bar->finish();
        $this->newLine();
        $this->info("{$result['created']} pares aprovados e salvos. {$result['skipped']} já existiam e foram pulados.");
    }

    protected function resolveCommitteeIds(): ?array
    {
        if ($this->option('ids')) {
            return collect(explode(',', $this->option('ids')))
                ->map(fn ($id) => (int) trim($id))
                ->filter()
                ->values()
                ->all();
        }

        if ($this->option('from') && $this->option('to')) {
            return range((int) $this->option('from'), (int) $this->option('to'));
        }

        return null; // sem filtro = todas as comissões
    }
}