<?php

namespace App\Services\News;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GroqSummarizerService
{
    /**
     * Gera um resumo de IA para a notícia, alternando entre as chaves da Groq
     * configuradas para distribuir a carga e evitar que uma única chave esgote sua cota.
     *
     * @return array{resumo:string, relevancia:int, palavras_chave:array<int,string>}
     */
    public function resumir(string $titulo, string $conteudo): array
    {
        $chaves = $this->chavesDisponiveis();

        if (empty($chaves)) {
            throw new RuntimeException('Nenhuma chave da Groq configurada (GROQ_API_KEY_1/2/3).');
        }

        $indiceInicial = crc32($titulo) % count($chaves);
        $ultimoErro = null;

        for ($tentativa = 0; $tentativa < count($chaves); $tentativa++) {
            $chave = $chaves[($indiceInicial + $tentativa) % count($chaves)];

            try {
                return $this->chamarGroq($chave, $titulo, $conteudo);
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

    private function chamarGroq(string $chave, string $titulo, string $conteudo): array
    {
        $resposta = Http::withToken($chave)
            ->baseUrl(config('services.groq.base_url'))
            ->timeout(60)
            ->acceptJson()
            ->post('/chat/completions', [
                'model' => config('services.groq.model'),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você resume notícias políticas brasileiras para o Votus, um sistema de '
                            . 'transparência política. Responda SEMPRE com um JSON válido contendo as chaves: '
                            . '"resumo" (string, até 3 parágrafos curtos, neutro e objetivo, em português), '
                            . '"relevancia" (inteiro de 0 a 10, o quão relevante a notícia é para o acompanhamento '
                            . 'de política e legislação no Brasil) e "palavras_chave" (array com até 6 strings).',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Título: {$titulo}\n\nConteúdo:\n{$conteudo}",
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

        return $this->interpretarResposta($texto);
    }

    private function interpretarResposta(string $texto): array
    {
        $dados = json_decode($texto, true);

        if (!is_array($dados) || empty($dados['resumo'])) {
            return [
                'resumo' => trim($texto),
                'relevancia' => 5,
                'palavras_chave' => [],
            ];
        }

        return [
            'resumo' => trim((string) $dados['resumo']),
            'relevancia' => max(0, min(10, (int) ($dados['relevancia'] ?? 5))),
            'palavras_chave' => array_map('strval', array_slice((array) ($dados['palavras_chave'] ?? []), 0, 6)),
        ];
    }
}
