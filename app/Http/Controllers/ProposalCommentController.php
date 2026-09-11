<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Http\Resources\ProposalCommentResource;
use App\Models\Proposal;
use Illuminate\Http\Request;

class ProposalCommentController extends Controller
{
    public function index(int $id)
    {
        $proposal = Proposal::where('status', ProposalStatus::Published)->findOrFail($id);

        $comments = $proposal->comments()->orderByDesc('created_at')->paginate(20);

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

        $comment = $proposal->comments()->create([
            'visitor_id' => (is_string($visitorId) && strlen($visitorId) <= 100) ? $visitorId : null,
            'author_name' => $data['author_name'] ?? null,
            'content' => $data['content'],
        ]);

        return new ProposalCommentResource($comment);
    }
}
