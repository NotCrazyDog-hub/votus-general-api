<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SchedulerController extends Controller
{
    /** Retorna uma resposta simples de saúde para confirmar que a API está acessível. */
    public function status(): JsonResponse
    {
        return response()->json(['status' => 'alive']);
    }
}
