<?php

namespace App\Domains\GovernmentPlans\Http\Controllers;

use App\Domains\GovernmentPlans\Http\Resources\GovernmentPlanThemeDetailResource;
use App\Domains\GovernmentPlans\Http\Resources\GovernmentPlanThemeResource;
use App\Domains\GovernmentPlans\Services\PresidentialProposalComparisonService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PresidentialProposalComparisonController extends Controller
{
    public function __construct(private readonly PresidentialProposalComparisonService $service)
    {
    }

    public function themes(): AnonymousResourceCollection
    {
        return GovernmentPlanThemeResource::collection($this->service->themes());
    }

    public function show(Request $request, string $theme): GovernmentPlanThemeDetailResource
    {
        $data = $request->validate([
            'candidate_one_id' => ['nullable', 'integer', 'exists:candidates,id'],
            'candidate_two_id' => ['nullable', 'integer', 'different:candidate_one_id', 'exists:candidates,id'],
        ]);

        $theme = $this->service->byTheme($theme);
        $comparison = $this->service->comparisonFor(
            $theme,
            $data['candidate_one_id'] ?? null,
            $data['candidate_two_id'] ?? null,
        );

        return new GovernmentPlanThemeDetailResource($theme, $comparison);
    }
}