<?php

namespace App\Domains\CivicParticipation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalComment extends Model
{
    protected $fillable = [
        'proposal_id',
        'visitor_id',
        'author_name',
        'content',
    ];

    /**
     * Cada registro guarda a referência para um único `Proposal`; vários registros podem compartilhar esse mesmo registro.
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
