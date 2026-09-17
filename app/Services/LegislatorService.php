<?php

namespace App\Services;

use App\Models\Legislator;

class LegislatorService
{
    public function listByChamber(string $chamber, ?string $state = null)
    {
        return Legislator::where('chamber', $chamber)
            ->when($state, fn ($q) => $q->where('state', $state))
            // Sem isso, LegislatorsResource::whenLoaded('thematicFocusTopTopic')
            // nunca via a relação carregada e a chave "top_topic" simplesmente
            // sumia do JSON da listagem (só aparecia no show/detalhe).
            ->with('thematicFocusTopTopic')
            ->orderBy('parliamentary_name')
            ->paginate(50);
    }

    public function findByChamber(int $external_id, string $chamber): Legislator
    {
        return Legislator::where('external_id', $external_id)
            ->where('chamber', $chamber)
            ->with(['committees', 'bills.topics', 'professions', 'thematicFocusTopTopic'])
            ->firstOrFail();
    }
}