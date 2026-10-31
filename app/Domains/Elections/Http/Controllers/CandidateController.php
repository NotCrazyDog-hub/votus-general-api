<?php

namespace App\Domains\Elections\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Domains\Elections\Services\CandidateService;
use App\Domains\Elections\Enums\CandidateOffice;
use App\Domains\Elections\Http\Resources\CandidateResource;
use App\Domains\Elections\Http\Resources\CandidateExpenseResource;
use App\Http\Controllers\Controller;

class CandidateController extends Controller
{
    public function __construct(protected CandidateService $service) {}

    /** Retorna a lista paginada de candidatos à Presidência com os filtros da requisição. */
    public function indexForPresidents(Request $request)
    {
        return $this->indexFor(CandidateOffice::President, $request);
    }

    /** Busca os detalhes de um candidato à Presidência pelo identificador do TSE. */
    public function showPresident(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::President)
        );
    }

    /** Retorna despesas paginadas do candidato à Presidência com os filtros informados. */
    public function presidentExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::President);
    }

    /** Retorna a lista paginada de candidatos a governador com os filtros da requisição. */
    public function indexForGovernors(Request $request)
    {
        return $this->indexFor(CandidateOffice::Governor, $request);
    }

    /** Busca os detalhes de um candidato a governador pelo identificador do TSE. */
    public function showGovernor(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::Governor)
        );
    }

    /** Retorna despesas paginadas do candidato a governador com os filtros informados. */
    public function governorExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::Governor);
    }

    /** Retorna a lista paginada de candidatos ao Senado com os filtros da requisição. */
    public function indexForSenateCandidates(Request $request)
    {
        return $this->indexFor(CandidateOffice::Senator, $request);
    }

    /** Busca os detalhes de um candidato ao Senado pelo identificador do TSE. */
    public function showSenateCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::Senator)
        );
    }

    /** Retorna despesas paginadas do candidato ao Senado com os filtros informados. */
    public function senateExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::Senator);
    }

    /** Retorna a lista paginada de candidatos a deputado federal com os filtros da requisição. */
    public function indexForFederalDeputyCandidates(Request $request)
    {
        return $this->indexFor(CandidateOffice::FederalDeputy, $request);
    }

    /** Busca os detalhes de um candidato a deputado federal pelo identificador do TSE. */
    public function showFederalDeputyCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::FederalDeputy)
        );
    }

    /** Retorna despesas paginadas do candidato a deputado federal com os filtros informados. */
    public function federalDeputyExpenses(Request $request, int $external_id)
    {
        return $this->expensesFor($request, $external_id, CandidateOffice::FederalDeputy);
    }

    /** Retorna a lista paginada de candidatos a deputado estadual com os filtros da requisição. */
    public function indexForStateDeputyCandidates(Request $request)
    {
        return $this->indexFor(CandidateOffice::StateDeputy, $request);
    }

    /** Busca os detalhes de um candidato a deputado estadual pelo identificador do TSE. */
    public function showStateDeputyCandidate(int $external_id)
    {
        return new CandidateResource(
            $this->service->findByOffice($external_id, CandidateOffice::StateDeputy)
        );
    }

    /** Retorna despesas paginadas do candidato a deputado estadual com os filtros informados. */
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
            // Situação eleitoral (resultado oficial do TSE).
            'election_result' => ['nullable', 'string', Rule::in(CandidateService::ELECTION_RESULT_FILTERS)],
        ]);

        $state = $validated['state'] ?? null;

        $candidates = $this->service->listByOffice(
            $office,
            $state,
            $validated['party'] ?? null,
            $validated['search'] ?? null,
            $validated['election_result'] ?? null,
        );

        return CandidateResource::collection($candidates)->additional([
            'filters' => [
                'parties' => $this->service->partiesByOffice($office, $state),
                'with_proposal_document' => $this->service->countWithProposalDocumentByOffice($office, $state),
                'with_higher_education' => $this->service->countWithHigherEducationByOffice($office, $state),
                'with_full_ticket' => $this->service->countWithFullTicketByOffice($office, $state),
                'previously_elected' => $this->service->countPreviouslyElectedByOffice($office, $state),
                'election_results' => $this->service->countsByElectionResult($office, $state),
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
