<?php

namespace App\Domains\GovernmentPlans\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovernmentPlanTheme extends Model
{
    protected $fillable = ['slug', 'name', 'position'];

    public function proposals(): HasMany
    {
        return $this->hasMany(GovernmentPlanProposal::class);
    }

    public function comparisons(): HasMany
    {
        return $this->hasMany(GovernmentPlanComparison::class);
    }
}
