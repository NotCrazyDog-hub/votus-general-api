<?php

namespace App\Services\Metrics;

use App\Models\Legislator;

class ThematicConsistencyService
{
    public function calculate(Legislator $legislator): array
    {
        $committeeIds = $legislator->committees()->pluck('committees.id');

        $relevantTopicIds = \App\Models\CommitteeTopic::whereIn('committee_id', $committeeIds)
            ->where('reviewed', true)
            ->pluck('topic_id');

        $bills = $legislator->bills()->with('topics')->get();
        $total = $bills->count();

        if ($total === 0) {
            return ['total' => 0, 'consistent' => 0, 'rate' => null, 'wilson_lower' => null];
        }

        $consistent = $bills->filter(function ($bill) use ($relevantTopicIds) {
            return $bill->topics->pluck('id')->intersect($relevantTopicIds)->isNotEmpty();
        })->count();

        return [
            'total' => $total,
            'consistent' => $consistent,
            'rate' => round($consistent / $total, 4),
            'wilson_lower' => $this->wilsonLowerBound($consistent, $total),
        ];
    }

    protected function wilsonLowerBound(int $x, int $n, float $z = 1.96): float
    {
        if ($n === 0) {
            return 0.0;
        }

        $p = $x / $n;
        $denominator = 1 + ($z ** 2) / $n;
        $center = $p + ($z ** 2) / (2 * $n);
        $margin = $z * sqrt(($p * (1 - $p) / $n) + ($z ** 2) / (4 * $n ** 2));

        return round(($center - $margin) / $denominator, 4);
    }
}