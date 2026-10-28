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

    /**
     * Tudo de uma vez (candidatos, temas e propostas, ~160 itens) para a
     * busca por palavra-chave e a visão geral do front, sem uma requisição
     * por tema. As comparações por tema continuam em show().
     */
    public function proposals()
    {
        $proposals = $this->service->allProposals();

        return response()->json([
            'data' => [
                'candidates' => $this->service->candidates()->map(fn ($candidate) => [
                    'id' => $candidate->id,
                    'external_id' => $candidate->external_id,
                    'name' => $candidate->ballot_name,
                    'party' => $candidate->party_acronym,
                    'plan_document_url' => $candidate->proposal_document_url,
                ])->values(),
                'themes' => $proposals->pluck('theme')->unique('id')->sortBy('name')->map(fn ($theme) => [
                    'slug' => $theme->slug,
                    'name' => $theme->name,
                ])->values(),
                'proposals' => $proposals->map(fn ($proposal) => [
                    'id' => $proposal->id,
                    'candidate_id' => $proposal->candidate_id,
                    'theme_slug' => $proposal->theme->slug,
                    'title' => $proposal->title,
                    'description' => $proposal->description,
                    'source_excerpt' => $proposal->source_excerpt,
                    'source_reference' => $proposal->source_reference,
                    'source_url' => $proposal->source_url,
                    // 'literal' (texto copiado da fonte) ou 'ai' (resumo da IA).
                    'extraction' => $proposal->extractionMethod(),
                ])->values(),
            ],
        ]);
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
                                'extraction' => $proposal->extractionMethod(),
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
