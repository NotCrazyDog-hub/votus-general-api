<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    public function states(): JsonResponse
    {
        try {
            $states = Cache::remember(
                'ibge:states',
                now()->addDays(30),
                function () {
                    return Http::timeout(15)
                        ->retry(2, 500)
                        ->get(
                            'https://servicodados.ibge.gov.br/api/v1/localidades/estados',
                            ['orderBy' => 'nome']
                        )
                        ->throw()
                        ->json();
                }
            );

            return response()->json($states);
        } catch (RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível consultar os estados.',
            ], 503);
        }
    }

    public function cities(int $stateId): JsonResponse
    {
        abort_unless($stateId >= 10 && $stateId <= 99, 404);

        try {
            $cities = Cache::remember(
                "ibge:state:{$stateId}:cities",
                now()->addDays(30),
                function () use ($stateId) {
                    return Http::timeout(15)
                        ->retry(2, 500)
                        ->get(
                            "https://servicodados.ibge.gov.br/api/v1/localidades/estados/{$stateId}/municipios",
                            ['orderBy' => 'nome']
                        )
                        ->throw()
                        ->json();
                }
            );

            return response()->json($cities);
        } catch (RequestException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Não foi possível consultar os municípios.',
            ], 503);
        }
    }
}