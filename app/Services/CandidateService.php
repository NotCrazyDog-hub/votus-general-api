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
        'judgment_status',
    ];

    public function listByOffice(
        CandidateOffice $office,
        ?string $state = null,
        ?string $party = null,
        ?string $search = null,
    ) {
        $search = $search !== null ? trim($search) : null;

        // paginate() (era simplePaginate): o front precisa do total real de
        // páginas — ver o mesmo comentário em LegislatorService::listByChamber.
        // Sem isso, o botão "próxima" nunca desabilitava de verdade e o
        // número de páginas exibido crescia a cada clique.
        return Candidate::titulares()
            ->select(self::LIST_COLUMNS)
            ->where('office_name', $office->toTseDescription())
            ->deferidos()
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
            ->paginate(50)
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
                ->deferidos()
                ->when($state, fn ($q) => $q->where('state', $state))
                ->whereNotNull('party_acronym')
                ->distinct()
                ->orderBy('party_acronym')
                ->pluck('party_acronym')
                ->all(),
        );
    }

    /**
     * Quantos candidatos titulares do cargo têm plano de governo anexado
     * (proposal_document_path). Igual a partiesByOffice: precisa contar
     * entre TODOS os candidatos do cargo, não só os 50 da página atual, daí
     * o mesmo cache de 1h em disco em vez de deixar o front somar sozinho.
     */
    public function comPropostaByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->contarComFiltro('com-proposta', $office, $state, fn ($q) => $q->whereNotNull('proposal_document_path'));
    }

    /**
     * Quantos declararam "SUPERIOR COMPLETO" como escolaridade — mesma
     * string usada pelo TSE em DS_GRAU_INSTRUCAO, confirmada nos dados reais.
     */
    public function comEnsinoSuperiorByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->contarComFiltro('ensino-superior', $office, $state, fn ($q) => $q->where('education_level', 'SUPERIOR COMPLETO'));
    }

    /**
     * Quantos têm vice ou suplentes registrados na chapa (running_mates) —
     * só existe pra Presidente/Governador/Senador; Deputado Federal/Estadual
     * não tem vice, então esse número vem sempre 0 pra esses dois cargos.
     */
    public function comChapaByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->contarComFiltro('com-chapa', $office, $state, fn ($q) => $q->whereHas('runningMates'));
    }

    /**
     * Quantos já tiveram mandato de parlamentar antes (CPF batendo com algum
     * registro em Legislator, ver Candidate::previousMandates).
     */
    public function jaFoiParlamentarByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->contarComFiltro('ja-foi-parlamentar', $office, $state, fn ($q) => $q->whereHas('previousMandates'));
    }

    /**
     * Base compartilhada pelos contadores agregados acima: candidatos
     * titulares deferidos do cargo, com um filtro extra, contados entre
     * TODOS (não só a página atual) e cacheados por 1h em disco — a lista
     * só muda quando o sync do TSE roda.
     */
    private function contarComFiltro(string $chaveCache, CandidateOffice $office, ?string $state, \Closure $filtro): int
    {
        return Cache::store('file')->remember(
            "candidates:{$chaveCache}:{$office->value}:".($state ?? 'all'),
            now()->addHour(),
            function () use ($office, $state, $filtro) {
                $query = Candidate::titulares()
                    ->where('office_name', $office->toTseDescription())
                    ->deferidos()
                    ->when($state, fn ($q) => $q->where('state', $state));

                return $filtro($query)->count();
            },
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
