<?php

namespace App\Services\Metrics;

use App\Models\Committee;
use App\Models\CommitteeTopic;
use App\Models\Topic;

class CommitteeTopicAiClassificationService
{
    public function __construct(protected CommitteeTopicAiReviewService $aiService) {}

    public function classifyAll(?callable $onProgress = null, ?array $committeeIds = null): array
    {
        $committeesQuery = Committee::query();

        if ($committeeIds !== null) {
            $committeesQuery->whereIn('id', $committeeIds);
        }

        $committees = $committeesQuery->get();
        $topics = Topic::all();

        $created = 0;
        $skipped = 0;

        foreach ($committees as $committee) {
            foreach ($topics as $topic) {
                if (CommitteeTopic::where('committee_id', $committee->id)
                    ->where('topic_id', $topic->id)
                    ->exists()) {
                    $skipped++;
                    if ($onProgress) $onProgress();
                    continue;
                }

                try {
                    $result = $this->aiService->evaluate(
                        $committee->name,
                        $committee->acronym ?? '',
                        $topic->name
                    );

                    if ($result['approved']) {
                        CommitteeTopic::create([
                            'committee_id' => $committee->id,
                            'topic_id' => $topic->id,
                            'ai_confidence' => $result['confidence'],
                            'ai_reasoning' => $result['reasoning'],
                        ]);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }

                usleep(1_500_000); // ~1.5s entre chamadas
                if ($onProgress) $onProgress();
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}