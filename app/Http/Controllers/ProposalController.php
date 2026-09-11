<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Enums\ProposalVoteType;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\ProposalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProposalController extends Controller
{
    public function __construct(protected ProposalService $service) {}

    public function index(Request $request)
    {
        $proposals = $this->service->list($this->visitorId($request));

        return ProposalResource::collection($proposals);
    }

    public function show(Request $request, int $id)
    {
        $proposal = $this->service->find($id, $this->visitorId($request));

        return new ProposalResource($proposal);
    }

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

    protected function visitorId(Request $request): ?string
    {
        $visitorId = $request->header('X-Visitor-Id');

        if (! $visitorId || ! is_string($visitorId) || strlen($visitorId) > 100) {
            return null;
        }

        return $visitorId;
    }
}
