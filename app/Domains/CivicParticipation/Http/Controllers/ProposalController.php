<?php

namespace App\Domains\CivicParticipation\Http\Controllers;

use App\Domains\CivicParticipation\Enums\ProposalStatus;
use App\Domains\CivicParticipation\Enums\ProposalVoteType;
use App\Domains\CivicParticipation\Http\Resources\ProposalResource;
use App\Domains\CivicParticipation\Models\Proposal;
use App\Domains\CivicParticipation\Services\ProposalService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    public function __construct(protected ProposalService $service) {}

    /** Lista propostas publicadas e anexa o voto associado ao visitante, se houver. */
    public function index(Request $request)
    {
        $proposals = $this->service->list($this->visitorId($request));

        return ProposalResource::collection($proposals);
    }

    /** Busca uma proposta publicada e inclui o estado do voto do visitante. */
    public function show(Request $request, int $id)
    {
        $proposal = $this->service->find($id, $this->visitorId($request));

        return new ProposalResource($proposal);
    }

    /** Valida e publica uma proposta, sincroniza suas categorias e devolve o recurso completo. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'author' => ['required', 'string', 'max:255'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:255'],
        ]);

        $proposal = Proposal::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'author' => $data['author'],
            'status' => ProposalStatus::Published,
        ]);

        $this->service->syncCategories($proposal, $data['categories'] ?? []);

        return new ProposalResource(
            $this->service->find($proposal->id, $this->visitorId($request))
        );
    }

    /** Valida e registra o voto do visitante na proposta publicada. */
    public function vote(Request $request, int $id)
    {
        $visitorId = $this->visitorId($request);

        if (! $visitorId) {
            return response()->json([
                'message' => 'Identificador de visitante ausente. Envie o cabeçalho X-Visitor-Id.',
            ], 422);
        }

        $data = $request->validate([
            'vote' => ['required', Rule::in([ProposalVoteType::Legal->value, ProposalVoteType::NotSupport->value])],
        ]);

        $proposal = Proposal::where('status', ProposalStatus::Published)->findOrFail($id);

        $this->service->vote($proposal, $visitorId, ProposalVoteType::from($data['vote']));

        return new ProposalResource($this->service->find($id, $visitorId));
    }

    /** Remove o voto do visitante e retorna a proposta com o estado atualizado. */
    public function deleteVote(Request $request, int $id)
    {
        $visitorId = $this->visitorId($request);

        if (! $visitorId) {
            return response()->json([
                'message' => 'Identificador de visitante ausente. Envie o cabeçalho X-Visitor-Id.',
            ], 422);
        }

        $proposal = Proposal::where('status', ProposalStatus::Published)->findOrFail($id);

        $this->service->deleteVote($proposal, $visitorId);

        return new ProposalResource($this->service->find($id, $visitorId));
    }

    /** Valida o cabeçalho de visitante e descarta valores ausentes ou longos demais. */
    protected function visitorId(Request $request): ?string
    {
        $visitorId = $request->header('X-Visitor-Id');

        if (! $visitorId || ! is_string($visitorId) || strlen($visitorId) > 100) {
            return null;
        }

        return $visitorId;
    }
}
