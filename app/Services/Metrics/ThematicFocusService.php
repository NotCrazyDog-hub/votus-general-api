<?php

namespace App\Services\Metrics;

use App\Models\Legislator;

class ThematicFocusService
{
    protected const MIN_BILLS_FOR_INDEX = 5;

    public function calculate(Legislator $legislator): array
    {
        $bills = $legislator->bills()->with('topics')->get();

        $topicCounts = $bills
            ->flatMap(fn ($bill) => $bill->topics->pluck('id'))
            ->countBy();

        $totalAssignments = $topicCounts->sum();

        if ($totalAssignments === 0 || $bills->count() < self::MIN_BILLS_FOR_INDEX) {
            return [
                'total_bills' => $bills->count(),
                'index' => null,
                'top_topic_id' => null,
                'top_topic_share' => null,
            ];
        }

        $shares = $topicCounts->map(fn ($count) => $count / $totalAssignments);

        $hhi = $shares->reduce(fn ($carry, $share) => $carry + ($share ** 2), 0.0);

        $topTopicId = $shares->sortDesc()->keys()->first();
        $topTopicShare = $shares->sortDesc()->first();

        return [
            'total_bills' => $bills->count(),
            'index' => round($hhi, 4),
            'top_topic_id' => $topTopicId,
            'top_topic_share' => round($topTopicShare, 4),
        ];
    }
}