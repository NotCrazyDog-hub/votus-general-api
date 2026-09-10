<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'original_summary' => 'nullable|string',
            'ai_summary' => 'required|string',
            'url' => 'required|url',
            'image_url' => 'nullable|url',
            'site_logo_url' => 'nullable|url',
            'source' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
            'published_at' => 'required|date',
            'relevance_score' => 'required|integer|min:0|max:10',
            'keywords' => 'required|array',
            'keywords.*' => 'string',
            'published' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $news = News::firstOrCreate(
            ['url' => $data['url']],
            $data
        );

        return response()->json([
            'message' => $news->wasRecentlyCreated ? 'Notícia criada' : 'Notícia já existia',
            'data' => $news,
        ], $news->wasRecentlyCreated ? 201 : 200);
    }

    public function index(Request $request)
    {
        $query = News::query()->where('published', true);

        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->has('relevance_min')) {
            $query->where('relevance_score', '>=', $request->relevance_min);
        }

        $sortBy = $request->get('sort_by', 'published_at');
        $direction = $request->get('direction', 'desc');

        return response()->json(
            $query->orderBy($sortBy, $direction)->paginate(15)
        );
    }

    public function show(News $news)
    {
        return response()->json($news);
    }
}