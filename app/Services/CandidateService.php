<?php

namespace App\Services;

use App\Enums\CandidateOffice;
use App\Models\Candidate;
use Illuminate\Support\Facades\Cache;

class CandidateService
{
    // Só o que o CandidateResource usa na listagem. Sem isso vinha também o
    // raw_data (linha inteira do CSV do TSE, ~1,4KB por candidato) — 50 por
    // página trafegando do Supabase à toa, já que a listagem não o expõe.
    private const LIST_COLUMNS = [
        'id',
        'external_id',
        'ballot_number',
        'round',
        'state',
        'office_name',
        'civil_name',
        'ballot_name',
        'party_acronym',
        'party_name',
        'education_level',
        'occupation',
        'race_color',
        'photo_path',
        'proposal_document_path',
        'election_year',
    ];

    public function listByOffice(
        CandidateOffice $office,
        ?string $state = null,
        ?string $party = null,
        ?string $search = null,
    ) {
        $search = $search !== null ? trim($search) : null;

        // simplePaginate em vez de paginate: a mesma correção já aplicada em
        // LegislatorService::listByChamber — paginate() roda uma query extra
        // de COUNT que, no Supabase remoto, custa segundos por causa do
        // round-trip de rede, não da complexidade da query em si. Sem
        // last_page/total prontos, o front estima a última página pelo link
        // "next" (ver SimplePaginatedResponse no frontend).
        return Candidate::titulares()
            ->select(self::LIST_COLUMNS)
            ->where('office_name', $office->toTseDescription())
            ->when($state, fn ($q) => $q->where('state', $state))
            ->when($party, fn ($q) => $q->where('party_acronym', $party))
            ->when($search, function ($q) use ($search) {
                $termo = '%'.addcslashes($search, '\\%_').'%';

                $q->where(fn ($w) => $w
                    ->where('ballot_name', 'ilike', $termo)
                    ->orWhere('civil_name', 'ilike', $termo)
                    ->orWhere('party_acronym', 'ilike', $termo)
                    ->orWhere('ballot_number', 'like', $termo));
            })
            ->orderBy('ballot_name')
            ->simplePaginate(50)
            // Mantém party/search nos links "next"/"prev" da paginação.
            ->withQueryString();
    }

    /**
     * Partidos com candidatos titulares no cargo — alimenta o filtro de
     * partido do front, que não consegue montar essa lista sozinho porque só
     * recebe uma página (50) por vez. A lista só muda quando o sync do TSE
     * roda, então fica 1h em cache no disco local (não no store padrão, que
     * é o próprio banco e custaria a mesma ida ao Supabase que queremos
     * evitar).
     */
    public function partiesByOffice(CandidateOffice $office, ?string $state = null): array
    {
        return Cache::store('file')->remember(
            "candidates:parties:{$office->value}:".($state ?? 'all'),
            now()->addHour(),
            fn () => Candidate::titulares()
                ->where('office_name', $office->toTseDescription())
                ->when($state, fn ($q) => $q->where('state', $state))
                ->whereNotNull('party_acronym')
                ->distinct()
                ->orderBy('party_acronym')
                ->pluck('party_acronym')
                ->all(),
        );
    }

    public function findByOffice(int $externalId, CandidateOffice $office): Candidate
    {
        return Candidate::titulares()
            ->where('external_id', $externalId)
            ->where('office_name', $office->toTseDescription())
            ->with(['runningMates', 'previousMandates', 'candidacyHistory'])
            ->firstOrFail();
    }
}
