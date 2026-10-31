<?php

namespace App\Domains\CivicParticipation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = [
        'name',
    ];

    /**
     * Relação muitos-para-muitos entre Category e Proposal, mantida pela tabela intermediária do Eloquent.
     */
    public function proposals(): BelongsToMany
    {
        return $this->belongsToMany(Proposal::class);
    }
}
