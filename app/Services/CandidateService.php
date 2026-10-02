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
        return Candidate::mainCandidates()
            ->select(self::LIST_COLUMNS)
            ->where('office_name', $office->toTseDescription())
            ->approved()
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
            fn () => Candidate::mainCandidates()
                ->where('office_name', $office->toTseDescription())
                ->approved()
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
    public function countWithProposalDocumentByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->countWithFilter('with-proposal-document', $office, $state, fn ($q) => $q->whereNotNull('proposal_document_path'));
    }

    /**
     * Quantos declararam "SUPERIOR COMPLETO" como escolaridade — mesma
     * string usada pelo TSE em DS_GRAU_INSTRUCAO, confirmada nos dados reais.
     */
    public function countWithHigherEducationByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->countWithFilter('higher-education', $office, $state, fn ($q) => $q->where('education_level', 'SUPERIOR COMPLETO'));
    }

    /**
     * Quantos têm vice ou suplentes registrados na chapa (running_mates) —
     * só existe pra Presidente/Governador/Senador; Deputado Federal/Estadual
     * não tem vice, então esse número vem sempre 0 pra esses dois cargos.
     */
    public function countWithFullTicketByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->countWithFilter('full-ticket', $office, $state, fn ($q) => $q->whereHas('runningMates'));
    }

    /**
     * Quantos já foram eleitos antes, pra qualquer cargo — usa
     * candidacy_history (histórico real de candidaturas do TSE), não
     * Candidate::previousMandates: essa segunda só cruza CPF com a
     * legislatura ATUAL rastreada pelo Votus (só deputados/senadores em
     * exercício hoje), perdendo ex-governador, ex-prefeito ou quem foi
     * parlamentar numa legislatura passada — exatamente o problema já
     * corrigido no perfil individual do candidato (ver
     * CandidatoDetailClient.tsx no frontend, "Histórico de candidaturas").
     * Mesmo critério de "foi eleito" usado lá (foiEleito()): contém
     * "eleito" e não contém "não eleito"/"nao eleito".
     */
    public function countPreviouslyElectedByOffice(CandidateOffice $office, ?string $state = null): int
    {
        return $this->countWithFilter('previously-elected', $office, $state, fn ($q) => $q->whereHas(
            'candidacyHistory',
            fn ($h) => $h->where('result_status', 'ilike', '%eleito%')
                ->where('result_status', 'not ilike', '%não eleito%')
                ->where('result_status', 'not ilike', '%nao eleito%'),
        ));
    }

    /**
     * Base compartilhada pelos contadores agregados acima: candidatos
     * titulares deferidos do cargo, com um filtro extra, contados entre
     * TODOS (não só a página atual) e cacheados por 1h em disco — a lista
     * só muda quando o sync do TSE roda.
     */
    private function countWithFilter(string $cacheKey, CandidateOffice $office, ?string $state, \Closure $filter): int
    {
        return Cache::store('file')->remember(
            "candidates:{$cacheKey}:{$office->value}:".($state ?? 'all'),
            now()->addHour(),
            function () use ($office, $state, $filter) {
                $query = Candidate::mainCandidates()
                    ->where('office_name', $office->toTseDescription())
                    ->approved()
                    ->when($state, fn ($q) => $q->where('state', $state));

                return $filter($query)->count();
            },
        );
    }

    public function findByOffice(int $externalId, CandidateOffice $office): Candidate
    {
        return Candidate::mainCandidates()
            ->where('external_id', $externalId)
            ->where('office_name', $office->toTseDescription())
            // previousMandates.bills.topics: eager load pra evitar N+1 ao
            // montar o histórico legislativo no perfil (LegislatorSummaryResource).
            ->with(['runningMates', 'previousMandates.bills.topics', 'candidacyHistory'])
            ->firstOrFail();
    }
}
