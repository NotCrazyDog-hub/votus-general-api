<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Http\Resources\ProposalCommentResource;
use App\Models\Proposal;
use App\Models\ProposalComment;
use Illuminate\Http\Request;

class ProposalCommentController extends Controller
{
    public function index(Request $request, int $id)
    {
        $proposal = Proposal::where('status', ProposalStatus::Published)->findOrFail($id);
        $visitorId = $request->header('X-Visitor-Id');

        $comments = $proposal->comments()->orderByDesc('created_at')->paginate(20);

        foreach ($comments as $comment) {
            $comment->can_delete = $visitorId && $comment->visitor_id === $visitorId;
        }

        return ProposalCommentResource::collection($comments);
    }

    public function store(Request $request, int $id)
    {
        $proposal = Proposal::where('status', ProposalStatus::Published)->findOrFail($id);

        $data = $request->validate([
            'author_name' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $visitorId = $request->header('X-Visitor-Id');
        $visitorId = (is_string($visitorId) && strlen($visitorId) <= 100) ? $visitorId : null;

        $comment = $proposal->comments()->create([
            'visitor_id' => $visitorId,
            'author_name' => $data['author_name'] ?? null,
            'content' => $data['content'],
        ]);

        $comment->can_delete = $visitorId !== null;

        return new ProposalCommentResource($comment);
    }

    public function destroy(Request $request, int $id, int $commentId)
    {
        $visitorId = $request->header('X-Visitor-Id');

        $comment = ProposalComment::where('proposal_id', $id)->findOrFail($commentId);

        if (! $visitorId || $comment->visitor_id !== $visitorId) {
            return response()->json([
                'message' => 'Você só pode apagar os próprios comentários.',
            ], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Comentário removido.']);
    }
}
