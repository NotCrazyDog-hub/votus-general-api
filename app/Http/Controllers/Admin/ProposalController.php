<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProposalCommentResource;
use App\Models\Proposal;
use App\Models\ProposalComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProposalController extends Controller
{
    /**
     * Lista propostas para moderação. Ao contrário do endpoint público
     * (App\Http\Controllers\ProposalController), não filtra por status
     * published — o admin precisa ver tudo, inclusive as já removidas.
     */
    public function index(Request $request): JsonResponse
    {
        $busca = trim((string) $request->query('search', ''));

        $proposals = Proposal::query()
            ->with('categories:id,name')
            ->when($busca !== '', fn ($query) => $query
                ->where(fn ($q) => $q
                    ->where('title', 'like', "%{$busca}%")
                    ->orWhere('author', 'like', "%{$busca}%")))
            ->orderByDesc('created_at')
            ->paginate(15);

        $proposals->getCollection()->transform(fn (Proposal $proposal) => [
            'id' => $proposal->id,
            'title' => $proposal->title,
            'content' => $proposal->content,
            'author' => $proposal->author,
            'categories' => $proposal->categories->pluck('name')->values(),
            'status' => $proposal->status->value,
            'created_at' => $proposal->created_at,
        ]);

        return response()->json($proposals);
    }

    /**
     * Remove uma proposta indevida. Soft delete: marca status como Removed em
     * vez de apagar a linha, para o registro ficar auditável/reversível — o
     * endpoint público já filtra por status Published, então some da listagem
     * de qualquer forma.
     */
    public function destroy(int $id): JsonResponse
    {
        $proposal = Proposal::findOrFail($id);
        $proposal->update(['status' => ProposalStatus::Removed]);

        return response()->json(['message' => 'Proposta removida.']);
    }

    /**
     * Lista os comentários de uma proposta para moderação. Sem filtro por
     * status published (o admin pode moderar comentários de qualquer
     * proposta, inclusive já removidas) e sempre com can_delete=true, já que
     * a autorização aqui é o middleware admin, não o dono do comentário.
     */
    public function comments(int $id)
    {
        $proposal = Proposal::findOrFail($id);
        $comments = $proposal->comments()->orderByDesc('created_at')->paginate(20);

        foreach ($comments as $comment) {
            $comment->can_delete = true;
        }

        return ProposalCommentResource::collection($comments);
    }

    /**
     * Remove qualquer comentário como admin — diferente do endpoint público
     * (App\Http\Controllers\ProposalCommentController::destroy), que só
     * permite ao próprio autor (via visitor_id) apagar o comentário.
     */
    public function destroyComment(int $id, int $commentId): JsonResponse
    {
        $comment = ProposalComment::where('proposal_id', $id)->findOrFail($commentId);
        $comment->delete();

        return response()->json(['message' => 'Comentário removido.']);
    }
}
