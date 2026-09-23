<?php

namespace App\Services\Opportunities;

use App\Models\Campus;
use App\Models\CourseOffering;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;

class UniversityCatalogService
{
    /**
     * Filtra ofertas de curso pelos parâmetros validados
     * e devolve uma única página paginada.
     *
     * No controller original havia dois paginators
     * (público e privado) simultâneos. Aqui o setor
     * vira só mais um filtro: quem quiser os dois
     * setores separados faz duas chamadas.
     */
    public function filterOfferings(array $filters): Paginator
    {
        $query = CourseOffering::query()
            ->with([
                'campus.university.admissionMethods' =>
                    fn ($query) => $query
                        ->where('active', true)
                        ->orderBy('name'),
            ]);

        if (! empty($filters['state'])) {
            $query->whereHas(
                'campus',
                fn ($query) => $query->where(
                    'state',
                    strtoupper($filters['state'])
                )
            );
        }

        if (! empty($filters['city_code'])) {
            $query->whereHas(
                'campus',
                fn ($query) => $query->where(
                    'ibge_city_code',
                    $filters['city_code']
                )
            );
        }

        if (! empty($filters['course'])) {
            $query->where(
                'normalized_name',
                $filters['course']
            );
        }

        if (! empty($filters['modality'])) {
            $query->where(
                'modality',
                $filters['modality']
            );
        }

        if (! empty($filters['sector'])) {
            $query->whereHas(
                'campus.university',
                fn ($query) => $query->where(
                    'sector',
                    $filters['sector']
                )
            );
        }

        // simplePaginate: mesmo ajuste já aplicado em Opportunity/
        // PublicOpportunity/Legislator/Candidate — evita a query de COUNT,
        // cara no Supabase remoto (497 ofertas só no Ceará, sem filtro).
        return $query
            ->orderBy('name')
            ->simplePaginate(12)
            ->withQueryString();
    }

    public function states(): Collection
    {
        return Campus::query()
            ->select('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');
    }

    public function municipalities(string $state): Collection
    {
        return Campus::query()
            ->where('state', strtoupper($state))
            ->select(['ibge_city_code', 'city'])
            ->distinct()
            ->orderBy('city')
            ->get();
    }

    public function courses(?string $state = null, ?string $cityCode = null): Collection
    {
        $query = CourseOffering::query()
            ->select(['name', 'normalized_name'])
            ->distinct()
            ->orderBy('name');

        if ($state && $cityCode) {
            $query->whereHas(
                'campus',
                fn ($query) => $query
                    ->where('state', strtoupper($state))
                    ->where('ibge_city_code', $cityCode)
            );
        }

        return $query->get();
    }

    public function modalities(): Collection
    {
        return CourseOffering::query()
            ->whereNotNull('modality')
            ->select('modality')
            ->distinct()
            ->orderBy('modality')
            ->pluck('modality');
    }
}