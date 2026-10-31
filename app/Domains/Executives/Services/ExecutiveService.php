<?php

namespace App\Domains\Executives\Services;

use App\Domains\Executives\Models\Executive;

class ExecutiveService
{
    /** Lista executivos atuais por nível e, no caso estadual, pela UF solicitada. */
    public function listByLevel(string $level, ?string $state = null)
    {
        return Executive::where('level', $level)
            ->when($state, fn ($q) => $q->where('state', $state))
            ->where('is_current', true)
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();
    }

    /** Busca governador/vice-governador estadual atual e carrega suas ações mais recentes. */
    public function findStateExecutive(int $id): Executive
    {
        return Executive::where('id', $id)
            ->where('level', 'state')
            ->whereIn('office', [
                'governor',
                'vice_governor',
            ])
            ->where('is_current', true)
            ->with([
                'actions' => fn ($query) => $query
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('published_at'),
            ])
            ->firstOrFail();
    }

    /** Busca presidente/vice-presidente federal atual e carrega suas ações mais recentes. */
    public function findFederalExecutive(int $id): Executive
    {
        return Executive::where('id', $id)
            ->where('level', 'federal')
            ->whereIn('office', [
                'president',
                'vice_president',
            ])
            ->where('is_current', true)
            ->with([
                'actions' => fn ($query) => $query
                    ->orderByDesc('occurred_at')
                    ->orderByDesc('published_at'),
            ])
            ->firstOrFail();
    }
}
