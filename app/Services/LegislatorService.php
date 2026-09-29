<?php

namespace App\Services;

use App\Models\Legislator;

class LegislatorService
{
    public function listByChamber(string $chamber, ?string $state = null)
    {
        // paginate() (era simplePaginate): o front precisa do total real de
        // páginas pra desabilitar corretamente o botão "próxima" — com
        // simplePaginate ele só sabe se EXISTE uma próxima página, e ao
        // estimar o total incrementando a cada clique (page+1) o número de
        // páginas "crescia" indefinidamente. Essa listagem é pequena
        // (~20-30 registros, 1 página), então o COUNT extra é barato.
        return Legislator::where('chamber', $chamber)
            ->when($state, fn ($q) => $q->where('state', $state))
            ->with('thematicFocusTopTopic')
            ->orderBy('parliamentary_name')
            ->paginate(50)
            ->withQueryString();
    }

    public function findByChamber(int $external_id, string $chamber): Legislator
    {
        return Legislator::where('external_id', $external_id)
            ->where('chamber', $chamber)
            ->with(['committees', 'bills.topics', 'professions', 'thematicFocusTopTopic'])
            ->firstOrFail();
    }

    public function findByChamberSlug(string $source_slug, string $chamber): Legislator
    {
        return Legislator::where('source_slug', $source_slug)
            ->where('chamber', $chamber)
            ->with(['committees', 'bills.topics', 'professions', 'thematicFocusTopTopic'])
            ->firstOrFail();
    }
}