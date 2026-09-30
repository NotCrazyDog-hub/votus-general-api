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

    public function findByOffice(
        int $id,
        string $office,
        ?string $state = null
    ): Executive {
        return Executive::where('id', $id)
            ->where('office', $office)
            ->when($state, fn ($q) => $q->where('state', $state))
            ->where('is_current', true)
            ->with([
                'actions' => fn ($query) => $query
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('published_at'),
            ])
            ->firstOrFail();
    }
}