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

        // Até 15 rodadas: se todas as chaves baterem no limite de tokens por minuto (429),
        // espera o tempo sugerido pela Groq e tenta de novo.
        for ($round = 0; $round < 15; $round++) {
            $wait = 0.0;
            $rateLimited = 0;

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
                            // gpt-oss é um modelo de raciocínio: os tokens de "pensamento" contam no
                            // limite. Com 3000 o modelo gastava tudo raciocinando e a Groq devolvia
                            // json_validate_failed com failed_generation vazio.
                            'reasoning_effort' => config('services.groq.reasoning_effort', 'low'),
                            'max_completion_tokens' => 8000,
                            'messages' => $messages,
                        ]);

                    if ($response->status() === 429) {
                        $rateLimited++;
                        $wait = max($wait, (float) ($response->header('retry-after') ?: 5));
                        $lastError = new RuntimeException("Groq respondeu HTTP 429: {$response->body()}");

                        continue;
                    }

                    // Erro 400 é do prompt/payload, não da chave: trocar de chave não adianta.
                    if ($response->status() === 400) {
                        throw new RuntimeException("Groq respondeu HTTP 400: {$response->body()}");
                    }

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

                    if (str_starts_with($error->getMessage(), 'Groq respondeu HTTP 400')) {
                        throw new RuntimeException($error->getMessage(), previous: $error);
                    }
                }
            }

            // Só espera e repete se TODAS as chaves estavam limitadas por taxa.
            if ($rateLimited < count($keys)) {
                break;
            }

            usleep((int) (min(max($wait, 3.0), 60.0) * 1_000_000) + 500_000);
        }

        throw new RuntimeException('Todas as chaves da Groq falharam: ' . $lastError?->getMessage(), previous: $lastError);
    }
}