<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\OpportunityResource;
use App\Models\Opportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OpportunityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Opportunity::query()->where('is_active', true);

        if ($request->filled('type')) {
            $query->where('opportunity_type', $request->type);
        }

        if ($request->filled('location')) {
            $location = trim($request->location);
            $query->where('location', 'ilike', "%{$location}%");
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($query) use ($search) {
                $query
                    ->where('title', 'ilike', "%{$search}%")
                    ->orWhere('company', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // simplePaginate em vez de paginate: evita a query extra de COUNT,
        // que no Supabase remoto custa segundos de round-trip de rede (ver o
        // mesmo ajuste em LegislatorService/CandidateService). O front
        // estima a última página pelo link "next" em vez de usar total.
        $opportunities = $query
            ->orderByDesc('published_at')
            ->simplePaginate(12)
            ->withQueryString();

        return OpportunityResource::collection($opportunities);
    }
}