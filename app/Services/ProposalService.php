<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\ProposalVoteType;
use App\Models\Proposal;
use App\Models\ProposalVote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;

class ProposalService
{
    public function list(?string $visitorId): LengthAwarePaginator
    {
        $proposals = $this->baseQuery()
            ->orderByDesc('created_at')
            ->paginate(10);

        $this->attachViewerVotes($proposals->getCollection(), $visitorId);

        return $proposals;
    }

    public function find(int $id, ?string $visitorId): Proposal
    {
        $proposal = $this->baseQuery()->findOrFail($id);

        $this->attachViewerVotes(new Collection([$proposal]), $visitorId);

        return $proposal;
    }

    public function vote(Proposal $proposal, string $visitorId, ProposalVoteType $voteType): ProposalVote
    {
        try {
            return ProposalVote::updateOrCreate(
                ['proposal_id' => $proposal->id, 'visitor_id' => $visitorId],
                ['vote_type' => $voteType]
            );
        } catch (QueryException $exception) {
            // Two near-simultaneous requests from the same visitor raced past the
            // findOrNew check; the unique(proposal_id, visitor_id) constraint caught
            // the duplicate insert. Fall back to updating the row that won the race.
            return tap(
                ProposalVote::where('proposal_id', $proposal->id)
                    ->where('visitor_id', $visitorId)
                    ->firstOrFail()
            )->update(['vote_type' => $voteType]);
        }
    }

    protected function baseQuery()
    {
        return Proposal::where('status', ProposalStatus::Published)
            ->withCount([
                'votes as legal_votes_count' => fn ($query) => $query->where('vote_type', ProposalVoteType::Legal),
                'votes as not_support_votes_count' => fn ($query) => $query->where('vote_type', ProposalVoteType::NotSupport),
            ]);
    }

    protected function attachViewerVotes(Collection $proposals, ?string $visitorId): void
    {
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
