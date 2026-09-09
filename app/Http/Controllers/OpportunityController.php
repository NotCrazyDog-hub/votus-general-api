<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use Illuminate\Http\Request;

class OpportunityController extends Controller
{
    public function index(Request $request)
    {
        $query = Opportunity::query()
            ->where('is_active', true);


        // Tipo da oportunidade
        if ($request->filled('type')) {
            $query->where(
                'opportunity_type',
                $request->type
            );
        }


        // Localização
        if ($request->filled('location')) {
            $location = trim(
                $request->location
            );

            $query->where(
                'location',
                'ilike',
                "%{$location}%"
            );
        }


        // Pesquisa geral
        if ($request->filled('search')) {
            $search = trim(
                $request->search
            );

            $query->where(
                function ($q) use ($search) {
                    $q
                        ->where(
                            'title',
                            'ilike',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'company',
                            'ilike',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'ilike',
                            "%{$search}%"
                        );
                }
            );
        }


        $opportunities = $query
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        $locations = Opportunity::query()
            ->where('is_active', true)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');


        return view(
            'opportunities.index',
            compact('opportunities', 'locations')
        );
    }
}