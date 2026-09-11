<?php

namespace App\Console\Commands;

use App\Models\CommitteeTopic;
use App\Services\Metrics\CommitteeTopicMatchingService;
use Illuminate\Console\Command;

class GenerateCommitteeTopicMatches extends Command
{
    protected $signature = 'metrics:match-committee-topics
        {--min-score=0.3 : score mínimo pra considerar candidato}
        {--auto-approve=0.9 : score a partir do qual marca como revisado automaticamente}';

    protected $description = 'Gera candidatos de mapeamento comissão-tema por similaridade textual';

    public function handle(CommitteeTopicMatchingService $service)
    {
        $minScore = (float) $this->option('min-score');
        $autoApprove = (float) $this->option('auto-approve');

        $candidates = $service->generateCandidates($minScore);
        $this->info(count($candidates) . ' candidatos encontrados.');

        $bar = $this->output->createProgressBar(count($candidates));

        foreach ($candidates as $candidate) {
            CommitteeTopic::updateOrCreate(
                [
                    'committee_id' => $candidate['committee_id'],
                    'topic_id' => $candidate['topic_id'],
                ],
                [
                    'match_score' => $candidate['score'],
                    'match_method' => 'text_similarity',
                    'reviewed' => $candidate['score'] >= $autoApprove,
                    'reviewed_at' => $candidate['score'] >= $autoApprove ? now() : null,
                ]
            );

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $pendingReview = CommitteeTopic::where('reviewed', false)->count();
        $this->warn("{$pendingReview} pares aguardando revisão manual da equipe.");
    }
}