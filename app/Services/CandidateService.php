<?php

namespace App\Services;

use App\Enums\CandidateOffice;
use App\Models\Candidate;

class CandidateService
{
    public function listByOffice(CandidateOffice $office, ?string $state = null)
    {
        // simplePaginate em vez de paginate: a mesma correção já aplicada em
        // LegislatorService::listByChamber — paginate() roda uma query extra
        // de COUNT que, no Supabase remoto, custa segundos por causa do
        // round-trip de rede, não da complexidade da query em si. Sem
        // last_page/total prontos, o front estima a última página pelo link
        // "next" (ver SimplePaginatedResponse no frontend).
        return Candidate::titulares()
            ->where('office_name', $office->toTseDescription())
            ->when($state, fn ($q) => $q->where('state', $state))
            // ->with(['runningMates', 'previousMandates'])
            ->orderBy('ballot_name')
            ->simplePaginate(50);
    }

    public function findByOffice(int $externalId, CandidateOffice $office): Candidate
    {
        return Candidate::titulares()
            ->where('external_id', $externalId)
            ->where('office_name', $office->toTseDescription())
            ->with(['runningMates', 'previousMandates'])
            ->firstOrFail();
    }
}