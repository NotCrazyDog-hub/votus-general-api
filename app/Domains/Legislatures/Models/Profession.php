<?php

namespace App\Domains\Legislatures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profession extends Model
{
    protected $fillable = ['normalized_name', 'camara_code'];

    /**
     * Relação muitos-para-muitos entre Profession e Legislator, mantida pela tabela intermediária do Eloquent.
     */
    public function legislators(): BelongsToMany
    {
        return $this->belongsToMany(Legislator::class)
            ->withPivot(['source', 'original_name', 'is_primary', 'registered_at'])
            ->withTimestamps();
    }
}