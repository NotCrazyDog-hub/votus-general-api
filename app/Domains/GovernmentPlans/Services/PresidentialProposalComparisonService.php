<?php

namespace App\Domains\GovernmentPlans\Services;

use App\Domains\Elections\Models\Candidate;
use App\Domains\GovernmentPlans\Models\GovernmentPlanComparison;
use App\Domains\GovernmentPlans\Models\GovernmentPlanProposal;
use App\Domains\GovernmentPlans\Models\GovernmentPlanTheme;
use Illuminate\Support\Collection;

class PresidentialProposalComparisonService
{
    /**
     * Candidatos da comparação: os que têm fonte de plano configurada
     * (services.government_plans.sites) mais os que já têm propostas — assim
     * a tela mostra a coluna do candidato mesmo num tema sem proposta dele.
     */
    public function candidates(): Collection
    {
        $configured = array_map('strval', array_keys(config('services.government_plans.sites', [])));

        return Candidate::query()
            ->whereNull('running_mate_of_id')
            ->where(fn ($query) => $query
                ->whereIn('external_id', $configured)
                ->orWhereIn('id', GovernmentPlanProposal::query()->active()->select('candidate_id')))
            ->orderBy('ballot_name')
            ->get();
    }

    public function allProposals(): Collection
    {
        return GovernmentPlanProposal::query()
            ->active()
            ->with('theme:id,slug,name')
            ->orderBy('candidate_id')
            ->orderBy('id')
            ->get();
    }

    public function themes()
    {
        return GovernmentPlanTheme::query()
            ->whereHas('proposals', fn ($query) => $query->active())
            ->withCount(['proposals' => fn ($query) => $query->active()])
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    public function byTheme(string $slug): GovernmentPlanTheme
    {
        return GovernmentPlanTheme::query()
            ->where('slug', $slug)
            ->with(['proposals' => fn ($query) => $query->active()->with('candidate')])
            ->firstOrFail();
    }

    public function comparisonFor(GovernmentPlanTheme $theme, ?int $firstCandidateId, ?int $secondCandidateId): ?GovernmentPlanComparison
    {
        // Comparações antigas de temas em que só um candidato tem proposta
        // (geradas antes de existir esta regra) não são servidas: diziam que o
        // outro "não menciona" o tema, o que a lista extraída não prova.
        if ($theme->proposals->pluck('candidate_id')->unique()->count() < 2) {
            return null;
        }

        $query = $theme->comparisons()->with(['candidateOne', 'candidateTwo'])->latest('updated_at');

        if ($firstCandidateId && $secondCandidateId) {
            $ids = [$firstCandidateId, $secondCandidateId];
            sort($ids);
            $query->where('candidate_one_id', $ids[0])->where('candidate_two_id', $ids[1]);
        }

        return $query->first();
    }
}
