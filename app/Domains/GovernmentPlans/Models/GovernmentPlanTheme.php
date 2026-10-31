<?php

namespace App\Domains\GovernmentPlans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentPlanTheme extends Model
{
    protected $fillable = ['slug', 'name', 'position'];

    /**
     * Relação um-para-muitos com o modelo `GovernmentPlanProposal`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(GovernmentPlanProposal::class);
    }

    /**
     * Relação um-para-muitos com o modelo `GovernmentPlanComparison`: o registro atual pode estar associado a vários registros desse modelo.
     */
    public function comparisons(): HasMany
    {
        return $this->hasMany(GovernmentPlanComparison::class);
    }
}
