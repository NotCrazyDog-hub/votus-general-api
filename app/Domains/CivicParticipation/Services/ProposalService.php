<?php

namespace App\Domains\CivicParticipation\Services;

use App\Domains\CivicParticipation\Enums\ProposalStatus;
use App\Domains\CivicParticipation\Enums\ProposalVoteType;
use App\Domains\CivicParticipation\Models\Category;
use App\Domains\CivicParticipation\Models\Proposal;
use App\Domains\CivicParticipation\Models\ProposalVote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;

class ProposalService
{
    /**
     * Lista propostas publicadas, inclui categorias e contagens relacionadas
     * e anexa o voto do visitante sem consultar voto a voto.
     */
    public function list(?string $visitorId): LengthAwarePaginator
    {
        $proposals = $this->baseQuery()
            ->orderByDesc('created_at')
            ->paginate(10);

        $this->attachViewerVotes($proposals->getCollection(), $visitorId);

        return $proposals;
    }

    /** Busca uma proposta publicada e prepara o voto do visitante para o recurso. */
    public function find(int $id, ?string $visitorId): Proposal
    {
        $proposal = $this->baseQuery()->findOrFail($id);

        $this->attachViewerVotes(new Collection([$proposal]), $visitorId);

        return $proposal;
    }

    /**
     * Cria ou atualiza um voto por combinação proposta/visitante. Se duas
     * requisições concorrerem na inserção, atualiza a linha criada pela vencedora.
     */
    public function vote(Proposal $proposal, string $visitorId, ProposalVoteType $voteType): ProposalVote
    {
        try {
            return ProposalVote::updateOrCreate(
                ['proposal_id' => $proposal->id, 'visitor_id' => $visitorId],
                ['vote_type' => $voteType]
            );
        } catch (QueryException $exception) {
            // Duas requisições simultâneas do mesmo visitante podem tentar inserir o voto.
            // A restrição única de proposta/visitante impede a duplicação; neste caso,
            // buscamos e atualizamos a linha que a outra requisição conseguiu criar.
            return tap(
                ProposalVote::where('proposal_id', $proposal->id)
                    ->where('visitor_id', $visitorId)
                    ->firstOrFail()
            )->update(['vote_type' => $voteType]);
        }
    }

    /** Apaga o voto pertencente à combinação da proposta com este visitante. */
    public function deleteVote(Proposal $proposal, string $visitorId): void
    {
        ProposalVote::where('proposal_id', $proposal->id)
            ->where('visitor_id', $visitorId)
            ->delete();
    }

    /** Cria categorias ausentes e substitui os vínculos da proposta pelo conjunto recebido. */
    public function syncCategories(Proposal $proposal, array $names): void
    {
        $categoryIds = collect($names)
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn ($name) => Category::firstOrCreate(['name' => $name])->id);

        $proposal->categories()->sync($categoryIds);
    }

    protected function baseQuery()
    {
        // Centraliza a consulta para que listagem e detalhe usem os mesmos
        // filtros e contagens, evitando divergência entre as duas respostas.
        return Proposal::where('status', ProposalStatus::Published)
            ->with('categories:id,name')
            ->withCount([
                'votes as legal_votes_count' => fn ($query) => $query->where('vote_type', ProposalVoteType::Legal),
                'votes as not_support_votes_count' => fn ($query) => $query->where('vote_type', ProposalVoteType::NotSupport),
                'comments as comments_count',
            ]);
    }

    /** Anexa viewer_vote a cada proposta, usando uma consulta única para a coleção inteira. */
    protected function attachViewerVotes(Collection $proposals, ?string $visitorId): void
    {
        // Busca os votos do visitante numa única consulta para toda a coleção;
        // sem visitante, os itens recebem null sem consultar a tabela de votos.
        $votes = $visitorId
            ? ProposalVote::where('visitor_id', $visitorId)
                ->whereIn('proposal_id', $proposals->pluck('id'))
                ->pluck('vote_type', 'proposal_id')
            : collect();

        foreach ($proposals as $proposal) {
            $vote = $votes->get($proposal->id);
            $proposal->viewer_vote = $vote instanceof ProposalVoteType ? $vote->value : null;
        }
    }
}
