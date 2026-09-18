<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CandidateService;
use App\Enums\CandidateOffice;
use App\Http\Resources\CandidateResource;

class CandidateController extends Controller
{
    public function __construct(protected CandidateService $service) {}

    public function indexForGovernors(Request $request)
    {
        $candidates = $this->service->listByOffice(CandidateOffice::Governor, $request->state);
        return CandidateResource::collection($candidates);
    }

    public function showGovernor(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::Governor)
        );
    }

    public function indexForSenateCandidates(Request $request)
    {
        $candidates = $this->service->listByOffice(CandidateOffice::Senator, $request->state);
        return CandidateResource::collection($candidates);
    }

    public function showSenateCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::Senator)
        );
    }

    public function indexForFederalDeputyCandidates(Request $request)
    {
        $candidates = $this->service->listByOffice(CandidateOffice::FederalDeputy, $request->state);
        return CandidateResource::collection($candidates);
    }

    public function showFederalDeputyCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::FederalDeputy)
        );
    }

    public function indexForStateDeputyCandidates(Request $request)
    {
        $candidates = $this->service->listByOffice(CandidateOffice::StateDeputy, $request->state);
        return CandidateResource::collection($candidates);
    }

    public function showStateDeputyCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::StateDeputy)
        );
    }
}