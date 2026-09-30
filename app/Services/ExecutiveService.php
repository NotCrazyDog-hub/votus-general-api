<?php

namespace App\Services;

use App\Models\Executive;

class ExecutiveService
{
    public function listByLevel(string $level, ?string $state = null)
    {
        return Executive::where('level', $level)
            ->when($state, fn ($q) => $q->where('state', $state))
            ->where('is_current', true)
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();
    }

   public function findGovernorWithVice(int $id): array
    {
        $governor = Executive::where('id', $id)
            ->where('office', 'governor')
            ->where('is_current', true)
            ->with([
                'actions' => fn ($query) => $query
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('published_at'),
            ])
            ->firstOrFail();
    
        $viceGovernor = Executive::where('office', 'vice_governor')
            ->where('level', 'state')
            ->where('state', $governor->state)
            ->where('is_current', true)
            ->first();
    
        return [
            'governor' => $governor,
            'vice_governor' => $viceGovernor,
        ];
    }
}
