<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;

class ProposalController extends Controller
{
    /**
     * Lista propostas para moderação. Ao contrário do endpoint público
     * (App\Http\Controllers\ProposalController), não filtra por status
     * published — o admin precisa ver tudo, inclusive as já removidas.
     */
    public function index(): JsonResponse
    {
        $proposals = Proposal::query()
            ->with('categories:id,name')
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
}
