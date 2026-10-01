<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class AiAssistantController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        // 'mensagem'/'resposta': contrato com o webhook do n8n (configurado
        // fora deste repositório), não renomeado — ver decisão de escopo.
        $data = $request->validate([
            'mensagem' => [
                'required',
                'string',
                'min:2',
                'max:2000',
            ],
        ]);

        $webhookUrl = config('services.n8n.webhook_url');

        if (empty($webhookUrl)) {
            return response()->json([
                'message' => 'A URL do n8n não está configurada.',
            ], 500);
        }

        try {
            $response = Http::acceptJson()
                ->timeout(90)
                ->post($webhookUrl, [
                    'mensagem' => $data['mensagem'],
                ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'O n8n não conseguiu processar a pergunta.',
                    'status_n8n' => $response->status(),
                ], 502);
            }

            $answer = $response->json('resposta');

            if (!is_string($answer) || trim($answer) === '') {
                return response()->json([
                    'message' => 'O n8n retornou uma resposta inválida.',
                ], 502);
            }

            return response()->json([
                'resposta' => $answer,
            ]);
        } catch (Throwable $error) {
            report($error);

            return response()->json([
                'message' => 'Não foi possível conectar ao agente de IA.',
            ], 503);
        }
    }
}
