<?php

namespace App\Domains\GovernmentPlans\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GroqGovernmentPlanService
{
    public function extract(string $candidateName, string $documentPart): array
    {
        return $this->request([
            [
                'role' => 'system',
                'content' => 'Você é um pesquisador editorial imparcial do Votus. Extraia SOMENTE propostas '
                    . 'explicitamente presentes no trecho de plano de governo. Não acrescente contexto externo, '
                    . 'não presuma medidas e não faça julgamento. Responda JSON válido: {"proposals":[{'
                    . '"theme": string, "title": string, "description": string, "source_excerpt": string, '
                    . '"source_reference": string|null}]}. Use temas curtos e consistentes em português; '
                    . 'a descrição deve resumir a proposta em até duas frases e source_excerpt deve ser um trecho '
                    . 'literal curto que a sustente. source_reference deve ser null, pois a fonte é uma página web. '
                    . 'Se não houver proposta, retorne {"proposals":[]}.',
            ],
            [
                'role' => 'user',
                'content' => "Candidato: {$candidateName}\n\nTrecho do plano:\n{$documentPart}",
            ],
        ]);
    }

    public function compare(string $theme, array $proposalsByCandidate): array
    {
        return $this->request([
            [
                'role' => 'system',
                'content' => 'Você compara planos de governo de forma estritamente descritiva e imparcial. '
                    . 'Use apenas as propostas fornecidas. Não diga que uma proposta é melhor/pior, não complete '
                    . 'lacunas e deixe claro quando um plano não apresenta proposta sobre o tema. Responda JSON '
                    . 'válido: {"summary": string, "similarities": [string], "differences": [string], '
                    . '"candidate_summaries": {"nome do candidato": string}}. Cada item deve ser conciso.',
            ],
            [
                'role' => 'user',
                'content' => 'Tema: ' . $theme . "\n\nPropostas extraídas:\n"
                    . json_encode($proposalsByCandidate, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ],
        ]);
    }

    private function request(array $messages): array
    {
        $keys = array_values(array_filter(config('services.groq.api_keys', [])));

        if ($keys === []) {
            throw new RuntimeException('Nenhuma chave da Groq está configurada.');
        }

        $lastError = null;

        foreach ($keys as $key) {
            try {
                $response = Http::withToken($key)
                    ->baseUrl(config('services.groq.base_url'))
                    ->timeout(120)
                    ->acceptJson()
                    ->post('/chat/completions', [
                        'model' => config('services.groq.model'),
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => 0.1,
                        'max_tokens' => 3000,
                        'messages' => $messages,
                    ]);

                if ($response->failed()) {
                    throw new RuntimeException("Groq respondeu HTTP {$response->status()}: {$response->body()}");
                }

                $content = $response->json('choices.0.message.content');
                $data = is_string($content) ? json_decode($content, true) : null;

                if (!is_array($data)) {
                    throw new RuntimeException('A Groq retornou JSON inválido.');
                }

                return $data;
            } catch (Throwable $error) {
                $lastError = $error;
            }
        }

        throw new RuntimeException('Todas as chaves da Groq falharam: ' . $lastError?->getMessage(), previous: $lastError);
    }
}
