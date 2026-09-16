<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Http;

class CommitteeTopicAiReviewService
{
    public function evaluate(string $committeeName, string $committeeAcronym, string $topicName): array
    {
        $response = Http::withOptions(['verify' => false])
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('services.groq.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->retry(5, function (int $attempt, \Exception $exception) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException
                    && $exception->response->status() === 429) {
                    $retryAfter = $exception->response->header('Retry-After');
                    return $retryAfter ? ((int) $retryAfter * 1000) : 10000; // ms
                }

                return 2000;
            })
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você avalia se uma comissão legislativa brasileira tem relação temática direta com um tema legislativo oficial. Responda SEMPRE em JSON puro, sem markdown, no formato: {"approved": true|false, "confidence": 0.0 a 1.0, "reasoning": "justificativa breve em português"}',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Comissão: {$committeeName} ({$committeeAcronym})\nTema: {$topicName}\n\nEssa comissão trata regularmente de matérias relacionadas a esse tema? Avalie com base na função regimental típica dessa comissão.",
                    ],
                ],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Falha ao consultar Groq: ' . $response->status() . ' - ' . $response->body());
        }

        $raw = $response->json('choices.0.message.content');
        $parsed = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($parsed['approved'])) {
            throw new \RuntimeException('Resposta da IA não é um JSON válido: ' . $raw);
        }

        return [
            'approved' => (bool) $parsed['approved'],
            'confidence' => (float) $parsed['confidence'],
            'reasoning' => $parsed['reasoning'] ?? null,
        ];
    }
}