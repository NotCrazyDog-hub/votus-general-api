<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;

/**
 * Candidato da visão geral: os dados básicos mais o link do plano de governo.
 */
class GovernmentPlanOverviewCandidateResource extends GovernmentPlanCandidateResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'plan_document_url' => $this->proposal_document_url,
        ];
    }
}