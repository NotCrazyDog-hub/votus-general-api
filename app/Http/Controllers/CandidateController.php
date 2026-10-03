<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CandidateService;
use App\Enums\CandidateOffice;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\CandidateExpenseResource;

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

    public function presidentExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::President);
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

    public function governorExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::Governor);
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

    public function senateExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::Senator);
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

    public function federalDeputyExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::FederalDeputy);
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

    public function stateDeputyExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::StateDeputy);
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
                'with_proposal_document' => $this->service->countWithProposalDocumentByOffice($office, $state),
                'with_higher_education' => $this->service->countWithHigherEducationByOffice($office, $state),
                'with_full_ticket' => $this->service->countWithFullTicketByOffice($office, $state),
                'previously_elected' => $this->service->countPreviouslyElectedByOffice($office, $state),
            ],
        ]);
    }

    /**
     * Lista paginada das despesas de campanha de um candidato (?page=).
     * Hoje só tem dado real pros candidatos do Ceará (ver auditoria de
     * veracidade) — candidato de outro estado sem despesa importada
     * simplesmente devolve uma lista vazia, não um erro.
     */
    private function expensesFor(Request $request, int $externalId, CandidateOffice $office)
    {
        $candidate = $this->service->findByOffice($externalId, $office);

        $page = max(1, (int) $request->get('page', 1));

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'supplierType' => ['nullable', 'string', 'in:PESSOA FÍSICA,PESSOA JURÍDICA'],
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date'],
            'sort' => ['nullable', 'string', 'in:date_desc,date_asc,amount_desc,amount_asc'],
        ]);

        return CandidateExpenseResource::collection(
            $this->service->expensesPaginated($candidate->id, $page, $filters)
        );
    }
}
