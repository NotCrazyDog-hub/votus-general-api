<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TrustedSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedSourceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TrustedSource::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:255'],
        ]);

        $host = parse_url($validated['base_url'], PHP_URL_HOST);

        if (!$host) {
            return response()->json(['message' => 'A URL informada é inválida.'], 422);
        }

        $dominio = TrustedSource::normalizeDomain($host);
        $existente = TrustedSource::where('domain', $dominio)->first();

        if ($existente) {
            return response()->json([
                'message' => "O domínio \"{$dominio}\" já está cadastrado como fonte confiável (\"{$existente->name}\").",
            ], 422);
        }

        // Guarda só a raiz do site (esquema + host), não a URL completa que o
        // admin pode ter colado (ex: o link de uma matéria específica, com
        // caminho e parâmetros) — base_url representa a fonte em si, não uma
        // página dela.
        $esquema = parse_url($validated['base_url'], PHP_URL_SCHEME) ?: 'https';
        $baseUrl = "{$esquema}://{$host}/";

        $trustedSource = TrustedSource::create([
            'name' => $validated['name'],
            'domain' => $dominio,
            'base_url' => $baseUrl,
            'is_active' => true,
        ]);

        return response()->json($trustedSource, 201);
    }

    public function update(Request $request, TrustedSource $trustedSource): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $dominio = TrustedSource::normalizeDomain($validated['domain']);
        $existente = TrustedSource::where('domain', $dominio)->where('id', '!=', $trustedSource->id)->first();

        if ($existente) {
            return response()->json([
                'message' => "O domínio \"{$dominio}\" já está cadastrado como fonte confiável (\"{$existente->name}\").",
            ], 422);
        }

        $trustedSource->update([
            'name' => $validated['name'],
            'domain' => $dominio,
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json($trustedSource);
    }

    public function destroy(TrustedSource $trustedSource): JsonResponse
    {
        $trustedSource->delete();

        return response()->json(['message' => 'Fonte removida.']);
    }
}
