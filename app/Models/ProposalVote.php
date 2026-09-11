<?php

namespace App\Models;

use App\Enums\ProposalVoteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalVote extends Model
{
    protected $fillable = [
        'proposal_id',
        'visitor_id',
        'vote_type',
    ];

    protected $casts = [
        'vote_type' => ProposalVoteType::class,
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
