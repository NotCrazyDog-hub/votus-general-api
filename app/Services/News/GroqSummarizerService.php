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
     * Além do resumo, essa mesma chamada decide se a notícia é publicável no
     * Votus (relevante_votus) — o Votus não é um agregador geral de notícias,
     * então o filtro de conteúdo é aplicado aqui, e não como um segundo
     * sistema separado.
     *
     * @return array{resumo:string, relevancia:int, palavras_chave:array<int,string>, relevante_votus:bool}
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
                        'content' => 'Você é o filtro editorial do Votus, um sistema de transparência política '
                            . 'brasileiro. O Votus NÃO é um agregador geral de notícias — só publica conteúdo '
                            . 'relacionado a política, eleições, governo, cidadania, políticas públicas e temas '
                            . 'sociais de interesse coletivo (Poder Executivo, Legislativo e Judiciário; '
                            . 'instituições públicas; legislação; orçamento e decisões governamentais; educação, '
                            . 'saúde, segurança pública, meio ambiente, direitos humanos e ciência/tecnologia '
                            . 'quando ligados a políticas públicas ou governo; esporte e cultura só quando '
                            . 'houver financiamento público, legislação ou gestão governamental envolvida). '
                            . 'Rejeite loteria, entretenimento, celebridades, fofoca, resultados esportivos sem '
                            . 'relevância pública, curiosidades, acidentes isolados sem relevância institucional '
                            . 'e qualquer assunto viral sem relação política ou social — mesmo que a notícia '
                            . 'apenas cite de passagem uma palavra como "governo": a relação precisa ser central '
                            . 'ao assunto da notícia, não incidental. Pergunta-guia: essa notícia ajuda alguém a '
                            . 'entender política, eleições, governo, políticas públicas, cidadania ou uma questão '
                            . 'social de interesse coletivo? Responda SEMPRE com um JSON válido contendo as '
                            . 'chaves: "resumo" (string, até 3 parágrafos curtos, neutro e objetivo, em '
                            . 'português — só preencha algo além de vazio se relevante_votus for true), '
                            . '"relevancia" (inteiro de 0 a 10, o quão relevante a notícia é para o '
                            . 'acompanhamento de política e legislação no Brasil), "relevante_votus" (booleano: '
                            . 'true somente se a notícia se encaixa no escopo do Votus descrito acima, false '
                            . 'caso contrário) e "palavras_chave" (array com até 6 strings).',
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

        // Resposta malformada: falha fechada (relevante_votus = false) em vez
        // de publicar por padrão — o Votus não deve virar agregador geral só
        // porque a IA devolveu um JSON inesperado. Repare que "resumo" vazio
        // NÃO é malformado: é o esperado quando relevante_votus é false (a
        // notícia foi reprovada e não tem resumo pra mostrar).
        if (!is_array($dados) || !array_key_exists('resumo', $dados) || !is_string($dados['resumo'])) {
            return [
                'resumo' => trim($texto),
                'relevancia' => 5,
                'palavras_chave' => [],
                'relevante_votus' => false,
            ];
        }

        return [
            'resumo' => trim($dados['resumo']),
            'relevancia' => max(0, min(10, (int) ($dados['relevancia'] ?? 5))),
            'palavras_chave' => array_map('strval', array_slice((array) ($dados['palavras_chave'] ?? []), 0, 6)),
            'relevante_votus' => (bool) ($dados['relevante_votus'] ?? false),
        ];
    }
}
