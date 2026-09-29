<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicOpportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicOpportunityController extends Controller
{
    /**
     * Campos usados na moderação (lista + detalhe): os mesmos da
     * PublicOpportunityResource pública, mais o que só interessa ao admin
     * (id numérico, review_status, quantas publicações/retificações
     * existem, quando foi vista pela 1ª/última vez pelo import do n8n). A
     * Resource pública não expõe esses campos de propósito — quem consome
     * a listagem pública (Juventude em Pauta) não precisa saber o status de
     * moderação nem controles internos.
     */
    private function toAdminArray(PublicOpportunity $opportunity, bool $comPublicacoes = false): array
    {
        $dados = [
            'id' => $opportunity->id,
            'source_key' => $opportunity->source_key,
            'type' => $opportunity->type,
            'title' => $opportunity->title,
            'notice_number' => $opportunity->notice_number,
            'agency' => $opportunity->agency,
            'municipality' => $opportunity->municipality,
            'state' => $opportunity->state,
            'positions' => $opportunity->positions,
            'education_levels' => $opportunity->education_levels,
            'vacancies' => $opportunity->vacancies,
            'salary_min' => $opportunity->salary_min,
            'salary_max' => $opportunity->salary_max,
            'registration_start' => $opportunity->registration_start?->toDateString(),
            'registration_end' => $opportunity->registration_end?->toDateString(),
            'exam_date' => $opportunity->exam_date?->toDateString(),
            'fee_min' => $opportunity->fee_min,
            'fee_max' => $opportunity->fee_max,
            'registration_url' => $opportunity->registration_url,
            'summary' => $opportunity->summary,
            // Status computado (aberto/em_breve/encerrado/indefinido) —
            // mesmo cálculo da listagem pública, só informativo aqui.
            'status' => $opportunity->status,
            // Status de moderação de verdade: é o que o admin usa pra
            // decidir aprovar/rejeitar/republicar.
            'review_status' => $opportunity->review_status,
            'publications_count' => $opportunity->publications_count ?? $opportunity->publications()->count(),
            'first_seen_at' => $opportunity->first_seen_at?->toIso8601String(),
            'last_seen_at' => $opportunity->last_seen_at?->toIso8601String(),
        ];

        if ($comPublicacoes) {
            $dados['publications'] = $opportunity->publications->map(fn ($publicacao) => [
                'publication_type' => $publicacao->publication_type,
                'gazette_date' => $publicacao->gazette_date?->toDateString(),
                'edition' => $publicacao->edition,
                'gazette_url' => $publicacao->gazette_url,
            ])->values();
        }

        return $dados;
    }

    /**
     * Lista as oportunidades para revisão, com contagem
     * de publicações e filtro por status/pesquisa.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PublicOpportunity::query()
            ->withCount('publications')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('review_status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($query) use ($search) {
                $query
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('agency', 'ilike', "%{$search}%")
                    ->orWhere('municipality', 'ilike', "%{$search}%")
                    ->orWhere('notice_number', 'ilike', "%{$search}%");
            });
        }

        $opportunities = $query->paginate(15)->withQueryString();
        $opportunities->getCollection()->transform(fn (PublicOpportunity $o) => $this->toAdminArray($o));

        return response()->json($opportunities);
    }

    /**
     * Detalhe de uma oportunidade para revisão/edição,
     * incluindo as publicações (editais, retificações etc).
     *
     * No admin, o abort_unless de review_status não se aplica:
     * é aqui que pending/rejected também precisam ser vistos.
     */
    public function show(PublicOpportunity $publicOpportunity): JsonResponse
    {
        $publicOpportunity->load([
            'publications' => fn ($query) => $query->orderByDesc('gazette_date'),
        ]);

        return response()->json(['data' => $this->toAdminArray($publicOpportunity, comPublicacoes: true)]);
    }

    /**
     * Salva as alterações feitas manualmente na revisão.
     */
    public function update(
        Request $request,
        PublicOpportunity $publicOpportunity
    ): JsonResponse {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:500'],
            'notice_number' => ['nullable', 'string', 'max:100'],
            'agency' => ['nullable', 'string', 'max:500'],
            'municipality' => ['nullable', 'string', 'max:200'],
            'state' => ['nullable', 'string', 'size:2'],
            'positions' => ['nullable', 'array'],
            'education_levels' => ['nullable', 'array'],
            'vacancies' => ['nullable', 'integer', 'min:0'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0'],
            'registration_start' => ['nullable', 'date'],
            'registration_end' => ['nullable', 'date', 'after_or_equal:registration_start'],
            'exam_date' => ['nullable', 'date'],
            'fee_min' => ['nullable', 'numeric', 'min:0'],
            'fee_max' => ['nullable', 'numeric', 'min:0'],
            'registration_url' => ['nullable', 'url', 'max:2000'],
            'summary' => ['nullable', 'string'],
        ]);

        if (! empty($data['state'])) {
            $data['state'] = strtoupper($data['state']);
        }

        $publicOpportunity->update($data);

        return response()->json(['data' => $this->toAdminArray($publicOpportunity)]);
    }

    /**
     * Aprova a oportunidade. Depois disso ela aparece
     * na página pública.
     */
    public function approve(PublicOpportunity $publicOpportunity): JsonResponse
    {
        $publicOpportunity->update(['review_status' => 'approved']);

        return response()->json(['data' => $this->toAdminArray($publicOpportunity)]);
    }

    /**
     * Descarta uma oportunidade. Não apagamos do banco
     * porque assim o n8n não recria a mesma oportunidade
     * (o source_key continua existindo, então o import
     * encontra o registro e só atualiza last_seen_at).
     */
    public function reject(PublicOpportunity $publicOpportunity): JsonResponse
    {
        $publicOpportunity->update(['review_status' => 'rejected']);

        return response()->json(['data' => $this->toAdminArray($publicOpportunity)]);
    }

    public function togglePublished(PublicOpportunity $publicOpportunity): JsonResponse
    {
        $publicOpportunity->update([
            'review_status' => $publicOpportunity->review_status === 'approved'
                ? 'pending'
                : 'approved',
        ]);

        return response()->json(['data' => $this->toAdminArray($publicOpportunity)]);
    }
}
