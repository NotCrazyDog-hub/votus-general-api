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

    protected function visitorId(Request $request): ?string
    {
        $visitorId = $request->header('X-Visitor-Id');

        if (! $visitorId || ! is_string($visitorId) || strlen($visitorId) > 100) {
            return null;
        }

        return $visitorId;
    }
}
