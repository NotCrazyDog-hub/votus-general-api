<?php

namespace App\Domains\GovernmentPlans\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\GovernmentPlans\Services\PresidentialProposalComparisonService;
use Illuminate\Http\Request;

class PresidentialProposalComparisonController extends Controller
{
    public function __construct(private readonly PresidentialProposalComparisonService $service)
    {
    }

    public function themes()
    {
        return response()->json([
            'data' => $this->service->themes()->map(fn ($theme) => [
                'slug' => $theme->slug,
                'name' => $theme->name,
                'proposals_count' => $theme->proposals_count,
            ]),
        ]);
    }

    public function show(Request $request, string $theme)
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

        return response()->json([
            'data' => [
                'theme' => ['slug' => $theme->slug, 'name' => $theme->name],
                'candidates' => $theme->proposals
                    ->groupBy('candidate_id')
                    ->map(function ($proposals) {
                        $candidate = $proposals->first()->candidate;

                        return [
                            'id' => $candidate->id,
                            'external_id' => $candidate->external_id,
                            'name' => $candidate->ballot_name,
                            'party' => $candidate->party_acronym,
                            'proposals' => $proposals->map(fn ($proposal) => [
                                'title' => $proposal->title,
                                'description' => $proposal->description,
                                'source_excerpt' => $proposal->source_excerpt,
                                'source_reference' => $proposal->source_reference,
                                'source_url' => $proposal->source_url,
                            ])->values(),
                        ];
                    })->values(),
                'comparison' => $comparison ? [
                    'summary' => $comparison->summary,
                    'similarities' => $comparison->similarities ?? [],
                    'differences' => $comparison->differences ?? [],
                    'candidate_summaries' => $comparison->candidate_summaries ?? [],
                    'candidate_one_id' => $comparison->candidate_one_id,
                    'candidate_two_id' => $comparison->candidate_two_id,
                ] : null,
            ],
        ]);
    }
}
