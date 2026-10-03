<?php

namespace App\Domains\Legislatures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profession extends Model
{
    protected $fillable = ['normalized_name', 'camara_code'];

    public function legislators(): BelongsToMany
    {
        return $this->belongsToMany(Legislator::class)
            ->withPivot(['source', 'original_name', 'is_primary', 'registered_at'])
            ->withTimestamps();
    }
}