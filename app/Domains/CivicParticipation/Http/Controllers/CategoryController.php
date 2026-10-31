<?php

namespace App\Domains\CivicParticipation\Http\Controllers;

use App\Domains\CivicParticipation\Models\Category;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CategoryController extends Controller
{
    /** Lista as categorias usadas pelas propostas, ordenadas para exibição na interface. */
    public function index(Request $request)
    {
        $search = $request->get('q');

        $categories = Category::when($search, fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json(['data' => $categories]);
    }
}
