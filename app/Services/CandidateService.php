<?php

namespace App\Services;

use App\Enums\CandidateOffice;
use App\Models\Candidate;

class CandidateService
{
    public function listByOffice(CandidateOffice $office, ?string $state = null)
    {
        return Candidate::titulares()
            ->where('office_name', $office->toTseDescription())
            ->when($state, fn ($q) => $q->where('state', $state))
            // ->with(['runningMates', 'previousMandates'])
            ->orderBy('ballot_name')
            ->paginate(50);
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