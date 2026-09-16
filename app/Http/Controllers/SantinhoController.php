<?php

namespace App\Http\Controllers;

use App\Models\SantinhoGeneration;
use Illuminate\Http\JsonResponse;

class SantinhoController extends Controller
{
    /**
     * Registra que um santinho foi gerado (a geração em si continua 100% no
     * client, via jsPDF — ver SantinhoPage/generateSantinhoPdf.ts). Sem esse
     * registro não existia nenhuma forma de contar quantos já foram gerados.
     */
    public function store(): JsonResponse
    {
        SantinhoGeneration::create();

        return response()->json(['message' => 'Registrado.'], 201);
    }
}
