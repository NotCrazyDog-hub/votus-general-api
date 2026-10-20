<?php

namespace App\Services\GovernmentPlans;

use App\Models\GovernmentPlanComparison;
use App\Models\GovernmentPlanTheme;

class PresidentialProposalComparisonService
{
    public function themes()
    {
        return GovernmentPlanTheme::query()
            ->whereHas('proposals')
            ->withCount('proposals')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    public function byTheme(string $slug): GovernmentPlanTheme
    {
        return GovernmentPlanTheme::query()
            ->where('slug', $slug)
            ->with(['proposals.candidate'])
            ->firstOrFail();
    }

    public function comparisonFor(GovernmentPlanTheme $theme, ?int $firstCandidateId, ?int $secondCandidateId): ?GovernmentPlanComparison
    {
        $query = $theme->comparisons()->with(['candidateOne', 'candidateTwo'])->latest('updated_at');

        if ($firstCandidateId && $secondCandidateId) {
            $ids = [$firstCandidateId, $secondCandidateId];
            sort($ids);
            $query->where('candidate_one_id', $ids[0])->where('candidate_two_id', $ids[1]);
        }

        return $query->first();
    }
}
