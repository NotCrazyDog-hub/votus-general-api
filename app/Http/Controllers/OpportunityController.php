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

        $opportunities = $query
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return OpportunityResource::collection($opportunities);
    }
}