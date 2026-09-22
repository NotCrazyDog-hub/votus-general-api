<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicOpportunityResource;
use App\Models\PublicOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicOpportunityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PublicOpportunity::query()
            ->where('review_status', 'approved');

        // Pesquisa pelo título, órgão ou município.
        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($query) use ($search) {
                $query
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('agency', 'ilike', "%{$search}%")
                    ->orWhere('municipality', 'ilike', "%{$search}%");
            });
        }

        // Concurso / processo seletivo etc.
        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        // Estado.
        if ($request->filled('state')) {
            $query->where('state', strtoupper($request->input('state')));
        }

        // Município.
        if ($request->filled('municipality')) {
            $query->where(
                'municipality',
                'ilike',
                '%' . trim($request->input('municipality')) . '%'
            );
        }

        // Primeiro os concursos cuja inscrição termina mais cedo.
        // Datas nulas ficam depois.
        $query->orderByRaw('registration_end IS NULL');
        $query->orderBy('registration_end');

        $opportunities = $query->paginate(12)->withQueryString();

        return PublicOpportunityResource::collection($opportunities);
    }

    public function show(PublicOpportunity $publicOpportunity): PublicOpportunityResource
    {
        // Impede acessar diretamente uma oportunidade
        // pendente/rejeitada só trocando a chave na URL.
        abort_unless(
            $publicOpportunity->review_status === 'approved',
            404
        );

        $publicOpportunity->load([
            'publications' => fn ($query) => $query->orderByDesc('gazette_date'),
        ]);

        return new PublicOpportunityResource($publicOpportunity);
    }
}