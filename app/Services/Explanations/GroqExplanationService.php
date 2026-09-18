<?php

namespace App\Services\Explanations;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GroqExplanationService
{
    // Teto defensivo do material de referência combinado (todas as fontes
    // juntas) — mesma lógica do GroqSummarizerService, protegendo contra
    // estourar o limite de tokens por minuto (TPM) da Groq numa única
    // chamada quando o admin cola várias fontes de uma vez.
    private const MAX_MATERIAL_CHARS = 8000;

    /**
     * Gera a explicação (7 campos fixos) e o quiz (5 perguntas, 4
     * alternativas cada, 1 correta) a partir do material de referência já
     * buscado e extraído das fontes confiáveis indicadas pelo admin —
     * mesma responsabilidade que o GroqSummarizerService tem pro resumo de
     * notícias, só que pro módulo de Explicações.
     *
     * @return array{explanation: array<string, string>, quiz: array<int, array<string, mixed>>}
     */
    public function gerar(string $perguntaTitulo, string $categoria, string $materialReferencia): array
    {
        $chaves = $this->chavesDisponiveis();

        if (empty($chaves)) {
            throw new RuntimeException('Nenhuma chave da Groq configurada (GROQ_API_KEY_1/2/3).');
        }

        if (mb_strlen($materialReferencia) > self::MAX_MATERIAL_CHARS) {
            $materialReferencia = mb_substr($materialReferencia, 0, self::MAX_MATERIAL_CHARS);
        }

        $indiceInicial = crc32($perguntaTitulo) % count($chaves);
        $ultimoErro = null;

        for ($tentativa = 0; $tentativa < count($chaves); $tentativa++) {
            $chave = $chaves[($indiceInicial + $tentativa) % count($chaves)];

            try {
                return $this->chamarGroq($chave, $perguntaTitulo, $categoria, $materialReferencia);
            } catch (Throwable $e) {
                $ultimoErro = $e;
            }
        }

        throw new RuntimeException(
            'Todas as chaves da Groq falharam: ' . $ultimoErro?->getMessage(),
            previous: $ultimoErro
        );
    }

    private function chavesDisponiveis(): array
    {
        return array_values(array_filter(config('services.groq.api_keys', [])));
    }

    private function chamarGroq(string $chave, string $perguntaTitulo, string $categoria, string $material): array
    {
        $resposta = Http::withToken($chave)
            ->baseUrl(config('services.groq.base_url'))
            ->timeout(90)
            ->acceptJson()
            ->post('/chat/completions', [
                'model' => config('services.groq.model'),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você escreve conteúdo educativo de cidadania e política para o Votus, uma '
                            . 'plataforma brasileira de transparência política. Baseie-se ESTRITAMENTE no material '
                            . 'de referência fornecido pelo usuário — não invente fatos, números ou eventos que '
                            . 'não estejam nele. Se o material não cobrir algum aspecto, responda de forma mais '
                            . 'genérica sobre esse aspecto em vez de inventar. Escreva em português claro e '
                            . 'acessível, para alguém sem conhecimento prévio de política. Responda SEMPRE com um '
                            . 'JSON válido no formato: {"explanation": {"summary": string (resposta rápida em até '
                            . '2 frases), "what_is": string, "purpose": string, "practical_role": string, '
                            . '"why_it_matters": string, "citizen_impact": string, "example": string}, "quiz": '
                            . '[array com EXATAMENTE 5 itens, cada um {"question": string, "explanation": string '
                            . '(explica por que a alternativa correta está certa), "options": [array com '
                            . 'EXATAMENTE 4 itens, cada um {"text": string, "correct": boolean}, sendo EXATAMENTE '
                            . ' 1 com correct=true]}]}. Cada campo de texto da explicação deve ter de 2 a 4 frases.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Pergunta: {$perguntaTitulo}\nCategoria: {$categoria}\n\n"
                            . "Material de referência (extraído das fontes indicadas):\n{$material}",
                    ],
                ],
            ]);

        if ($resposta->failed()) {
            throw new RuntimeException("Groq respondeu HTTP {$resposta->status()}: {$resposta->body()}");
        }

        $texto = $resposta->json('choices.0.message.content');

        if (!is_string($texto) || trim($texto) === '') {
            throw new RuntimeException('Groq retornou uma resposta vazia.');
        }

        $dados = json_decode($texto, true);

        if (!is_array($dados)) {
            throw new RuntimeException('Groq retornou um JSON inválido.');
        }

        return $dados;
    }
}
