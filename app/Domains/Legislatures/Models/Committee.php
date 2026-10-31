<?php

namespace App\Domains\Legislatures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Committee extends Model
{
    protected $fillable = [
        'external_id',
        'chamber',
        'name',
        'acronym',
    ];

    /**
     * Relação muitos-para-muitos entre Committee e Legislator, mantida pela tabela intermediária do Eloquent.
     */
    public function legislators(): BelongsToMany
    {
        return $this->belongsToMany(Legislator::class, 'committee_legislator')
            ->withPivot(['role', 'start_date', 'end_date']);
    }
}