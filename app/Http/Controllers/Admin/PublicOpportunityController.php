<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicOpportunityResource;
use App\Models\PublicOpportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicOpportunityController extends Controller
{
    /**
     * Lista as oportunidades para revisão, com contagem
     * de publicações e filtro por status/pesquisa.
     */
    public function index(Request $request): AnonymousResourceCollection
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

        return PublicOpportunityResource::collection($opportunities);
    }

    /**
     * Detalhe de uma oportunidade para revisão/edição,
     * incluindo as publicações (editais, retificações etc).
     *
     * No admin, o abort_unless de review_status não se aplica:
     * é aqui que pending/rejected também precisam ser vistos.
     */
    public function show(PublicOpportunity $publicOpportunity): PublicOpportunityResource
    {
        $publicOpportunity->load([
            'publications' => fn ($query) => $query->orderByDesc('gazette_date'),
        ]);

        return new PublicOpportunityResource($publicOpportunity);
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

        return response()->json(
            new PublicOpportunityResource($publicOpportunity)
        );
    }

    /**
     * Aprova a oportunidade. Depois disso ela aparece
     * na página pública.
     */
    public function approve(PublicOpportunity $publicOpportunity): JsonResponse
    {
        $publicOpportunity->update(['review_status' => 'approved']);

        return response()->json(
            new PublicOpportunityResource($publicOpportunity)
        );
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

        return response()->json(
            new PublicOpportunityResource($publicOpportunity)
        );
    }

    public function togglePublished(PublicOpportunity $publicOpportunity): JsonResponse
    {
        $publicOpportunity->update([
            'review_status' => $publicOpportunity->review_status === 'approved'
                ? 'pending'
                : 'approved',
        ]);

        return response()->json(
            new PublicOpportunityResource($publicOpportunity)
        );
    }
}