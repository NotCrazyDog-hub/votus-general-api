<?php

namespace App\Services;

use App\Models\Legislator;

class LegislatorService
{
    public function listByChamber(string $chamber, ?string $state = null)
    {
        // simplePaginate em vez de paginate: essa listagem sempre cabe numa
        // única página (~20-30 deputados/senadores no total), então a query
        // extra de COUNT que o paginate() roda pra saber o total de páginas
        // é puro custo sem benefício — e cada ida ao banco aqui tem um
        // custo de rede relevante (Supabase remoto), então cortar uma
        // dessas idas importa de verdade pro tempo de resposta.
        return Legislator::where('chamber', $chamber)
            ->when($state, fn ($q) => $q->where('state', $state))
            ->with('thematicFocusTopTopic')
            ->orderBy('parliamentary_name')
            ->simplePaginate(50);
    }

    public function findByChamber(int $external_id, string $chamber): Legislator
    {
        return Legislator::where('external_id', $external_id)
            ->where('chamber', $chamber)
            ->with(['committees', 'bills.topics', 'professions', 'thematicFocusTopTopic'])
            ->firstOrFail();
    }
}