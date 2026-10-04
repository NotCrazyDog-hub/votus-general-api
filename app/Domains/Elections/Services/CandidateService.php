<?php

namespace App\Domains\Elections\Services;

use App\Domains\Elections\Enums\CandidateOffice;
use App\Domains\Elections\Models\Candidate;
use App\Domains\Elections\Models\CandidateExpense;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $candidate = Candidate::mainCandidates()
            ->where('external_id', $externalId)
            ->where('office_name', $office->toTseDescription())
            // previousMandates.bills.topics: eager load pra evitar N+1 ao
            // montar o histórico legislativo no perfil (LegislatorSummaryResource).
            ->with(['runningMates', 'previousMandates.bills.topics', 'candidacyHistory'])
            ->firstOrFail();

        // Atributo dinâmico (não é coluna do banco): só a lista cabe aqui ser
        // carregada à parte via /expenses, paginada — alguns candidatos têm
        // mais de 1.200 despesas (ex: Elmano de Freitas), carregar tudo de
        // uma vez junto com o show() seria pesado demais. null quando o
        // candidato não declarou nenhuma despesa (hoje, só existe dado real
        // pros candidatos do Ceará — ver countPreviouslyElectedByOffice pro
        // mesmo tipo de limitação de cobertura).
        $candidate->expenses_summary = $this->expensesSummary($candidate->id);

        return $candidate;
    }

    /**
     * Resumo agregado (contagem + soma) das despesas de campanha de um
     * candidato — null se ele não tiver nenhuma declarada. O total é
     * CALCULADO pelo Votus a partir dos registros oficiais do TSE
     * (candidate_expenses), não é um campo que o TSE fornece pronto.
     */
    /**
     * O TSE publica relatórios financeiros periódicos durante a campanha —
     * cada um recebe SQ_DESPESA novos mesmo pra itens já declarados antes.
     * Somar todas as despesas desde o início da campanha duplicaria o
     * valor; o correto é olhar só o relatório mais recente de cada
     * candidato (confirmado comparando com o total oficial do TSE pro
     * Elmano de Freitas: nosso total batia ~2x o valor real antes disso).
     * Quando não há accounting_report_date (não deveria acontecer após o
     * backfill, mas por segurança), não filtra — melhor mostrar tudo que
     * temos do que esconder despesa real por falta desse campo.
     */
    private function latestReportExpenses(int $candidateId)
    {
        $latest = CandidateExpense::where('candidate_id', $candidateId)->max('accounting_report_date');

        return CandidateExpense::where('candidate_id', $candidateId)
            ->when($latest, fn ($q) => $q->where('accounting_report_date', $latest));
    }

    public function expensesSummary(int $candidateId): ?array
    {
        $summary = (clone $this->latestReportExpenses($candidateId))
            ->toBase()
            ->selectRaw('count(*) as count, sum(amount) as total, max(updated_at) as last_synced_at')
            ->first();

        if (! $summary || (int) $summary->count === 0) {
            return null;
        }

        // Só os 2 valores reais de supplier_type que o TSE usa — não é uma
        // categoria de gasto, é o tipo do fornecedor (pra uma mini
        // visualização legível, não pra fingir uma categorização que não existe).
        $porTipo = (clone $this->latestReportExpenses($candidateId))
            ->toBase()
            ->selectRaw('supplier_type, sum(amount) as total')
            ->groupBy('supplier_type')
            ->pluck('total', 'supplier_type');

        $maioresGastos = (clone $this->latestReportExpenses($candidateId))
            ->orderByDesc('amount')
            ->limit(3)
            ->get(['description', 'supplier_name', 'amount']);

        return [
            'count' => (int) $summary->count,
            'total' => (float) $summary->total,
            // Quando o Votus sincronizou essa despesa por último — não é um
            // dado que o TSE fornece, é da nossa própria coluna updated_at.
            'last_synced_at' => $summary->last_synced_at,
            'by_supplier_type' => [
                'pessoa_fisica' => (float) ($porTipo['PESSOA FÍSICA'] ?? 0),
                'pessoa_juridica' => (float) ($porTipo['PESSOA JURÍDICA'] ?? 0),
            ],
            'top_expenses' => $maioresGastos->map(fn ($e) => [
                'description' => $e->description,
                'supplier_name' => $e->supplier_name,
                'amount' => (float) $e->amount,
            ])->all(),
        ];
    }

    /**
     * Lista paginada das despesas de um candidato. Filtros e ordenação são
     * todos opcionais; sem nenhum, mantém o comportamento original (mais
     * recentes primeiro).
     *
     * @param array{search?: ?string, supplierType?: ?string, dateFrom?: ?string, dateTo?: ?string, sort?: ?string} $filters
     */
    public function expensesPaginated(int $candidateId, int $page = 1, array $filters = [])
    {
        $query = $this->latestReportExpenses($candidateId);

        if (! empty($filters['search'])) {
            $termo = '%'.addcslashes($filters['search'], '\\%_').'%';
            $query->where(fn ($q) => $q
                ->where('description', 'ilike', $termo)
                ->orWhere('supplier_name', 'ilike', $termo));
        }

        if (! empty($filters['supplierType'])) {
            $query->where('supplier_type', $filters['supplierType']);
        }

        if (! empty($filters['dateFrom'])) {
            $query->where('expense_date', '>=', $filters['dateFrom']);
        }

        if (! empty($filters['dateTo'])) {
            $query->where('expense_date', '<=', $filters['dateTo']);
        }

        match ($filters['sort'] ?? 'date_desc') {
            'date_asc' => $query->orderBy('expense_date'),
            'amount_desc' => $query->orderByDesc('amount'),
            'amount_asc' => $query->orderBy('amount'),
            default => $query->orderByDesc('expense_date'),
        };

        return $query->paginate(20, ['*'], 'page', $page);
    }
}
