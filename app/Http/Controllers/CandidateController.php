<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CandidateService;
use App\Enums\CandidateOffice;
use App\Http\Resources\CandidateResource;

class CandidateController extends Controller
{
    public function __construct(protected CandidateService $service) {}

    public function indexForPresidents(Request $request)
    {
        return $this->indexFor(CandidateOffice::President, $request);
    }

    public function showPresident(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::President)
        );
    }

    public function indexForGovernors(Request $request)
    {
        return $this->indexFor(CandidateOffice::Governor, $request);
    }

    public function showGovernor(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::Governor)
        );
    }

    public function indexForSenateCandidates(Request $request)
    {
        return $this->indexFor(CandidateOffice::Senator, $request);
    }

    public function showSenateCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::Senator)
        );
    }

    public function indexForFederalDeputyCandidates(Request $request)
    {
        return $this->indexFor(CandidateOffice::FederalDeputy, $request);
    }

    public function showFederalDeputyCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::FederalDeputy)
        );
    }

    public function indexForStateDeputyCandidates(Request $request)
    {
        return $this->indexFor(CandidateOffice::StateDeputy, $request);
    }

    public function showStateDeputyCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::StateDeputy)
        );
    }

    /**
     * Listagem por cargo com filtros opcionais (?party=PT&search=nome). Sem
     * nenhum dos dois, responde exatamente como antes — só ganha o bloco
     * "filters" com os partidos disponíveis pro select do front.
     */
    private function indexFor(CandidateOffice $office, Request $request)
    {
        $validated = $request->validate([
            'state' => ['nullable', 'string', 'size:2'],
            'party' => ['nullable', 'string', 'max:20'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $state = $validated['state'] ?? null;

        $candidates = $this->service->listByOffice(
            $office,
            $state,
            $validated['party'] ?? null,
            $validated['search'] ?? null,
        );

        return CandidateResource::collection($candidates)->additional([
            'filters' => [
                'parties' => $this->service->partiesByOffice($office, $state),
            ],
        ]);
    }
}
