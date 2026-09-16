<?php

namespace App\Http\Controllers;

use App\Models\SiteVisit;
use Illuminate\Http\JsonResponse;

class SiteVisitController extends Controller
{
    /**
     * Registra um acesso ao site. Anônimo e sem dados pessoais — só uma linha
     * com timestamp, deduplicada por sessão de navegador no frontend (ver
     * useSiteVisitPing no layout raiz). Conta "acessos", não "visitantes
     * únicos": o painel administrativo deve deixar essa distinção explícita.
     */
    public function store(): JsonResponse
    {
        SiteVisit::create();

        return response()->json(['message' => 'Registrado.'], 201);
    }
}
