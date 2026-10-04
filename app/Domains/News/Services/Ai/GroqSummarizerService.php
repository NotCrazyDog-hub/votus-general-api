<?php

namespace App\Domains\News\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GroqSummarizerService
{
    /**
     * Teto defensivo de tamanho do conteúdo enviado à Groq, independente do
     * chamador — protege contra artigos atipicamente longos que sozinhos
     * poderiam consumir boa parte do limite de tokens por minuto (TPM) da
     * API numa única chamada. ~6000 caracteres já cobre um texto jornalístico
     * completo em português (a imensa maioria dos artigos coletados fica bem
     * abaixo disso); o excesso é cortado sem prejudicar o resumo, já que o
     * essencial de uma notícia está sempre nos primeiros parágrafos.
     */
    private const MAX_CONTEUDO_CHARS = 6000;

    /**
     * Chave de cache do contador rotativo usado para espalhar as chamadas
     * entre as chaves da Groq (ver resumir()).
     */
    private const CACHE_INDICE_ROTATIVO = 'groq:proxima-chave';

    /**
     * Gera um resumo de IA para a notícia, alternando entre as chaves da Groq
     * configuradas para distribuir a carga e evitar que uma única chave esgote sua cota.
     *
     * A chave inicial de cada chamada vem de um contador rotativo atômico
     * (Cache::increment), não de um hash do título: com N chaves, a cada N
     * chamadas consecutivas cada uma é usada exatamente uma vez — o que um
     * hash não garante para lotes pequenos (ex.: 15 notícias / 5 chaves podia
     * sair 6/2/4/2/1 por coincidência de hash). Se a chave escolhida falhar,
     * tenta em sequência as demais chaves configuradas antes de desistir —
     * uma notícia só falha de vez se todas as chaves falharem.
     *
     * Além do resumo, essa mesma chamada decide se a notícia é publicável no
     * Votus (relevante_votus) — o Votus não é um agregador geral de notícias,
     * então o filtro de conteúdo é aplicado aqui, e não como um segundo
     * sistema separado.
     *
     * @return array{resumo:string, relevancia:int, palavras_chave:array<int,string>, relevante_votus:bool}
     */
    public function summarize(string $titulo, string $conteudo): array
    {
        $keys = $this->availableKeys();

        if (empty($keys)) {
            throw new RuntimeException('Nenhuma chave da Groq configurada (GROQ_API_KEY_1 a GROQ_API_KEY_5).');
        }

        if (mb_strlen($conteudo) > self::MAX_CONTEUDO_CHARS) {
            $conteudo = mb_substr($conteudo, 0, self::MAX_CONTEUDO_CHARS);
        }

        $totalKeys = count($keys);
        $startIndex = ($this->nextRotatingIndex() - 1) % $totalKeys;
        $lastError = null;

        for ($attempt = 0; $attempt < $totalKeys; $attempt++) {
            $position = ($startIndex + $attempt) % $totalKeys;
            $key = $keys[$position];

            try {
                return $this->callGroq($key, $titulo, $conteudo);
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning(sprintf(
                    '[NEWS] Chave Groq #%d de %d falhou, tentando próxima: %s',
                    $position + 1,
                    $totalKeys,
                    $e->getMessage()
                ));
            }
        }

        throw new RuntimeException(
            'Todas as chaves da Groq falharam: ' . $lastError?->getMessage(),
            previous: $lastError
        );
    }

    private function availableKeys(): array
    {
        return array_values(array_filter(config('services.groq.api_keys', [])));
    }

    /**
     * Cache::increment() sozinho, no driver 'database' (o usado em
     * produção), retorna false em vez de criar a chave quando ela ainda não
     * existe — descoberto em produção: isso zerava o índice pra false, que
     * em aritmética vira 0, e (0 - 1) % 5 dá -1 em PHP (o operador % daqui
     * não normaliza pra positivo como em outras linguagens), causando
     * "Undefined array key -1" ao indexar $chaves. Cache::add() garante que
     * a chave existe antes do increment; o "?: 1" é só uma rede de segurança
     * adicional caso increment falhe por outro motivo (nunca deixa cair pra
     * false/0 de novo).
     */
    private function nextRotatingIndex(): int
    {
        Cache::add(self::CACHE_INDICE_ROTATIVO, 0, now()->addDay());

        return Cache::increment(self::CACHE_INDICE_ROTATIVO) ?: 1;
    }

    private function callGroq(string $chave, string $titulo, string $conteudo): array
    {
        $resposta = Http::withToken($chave)
            ->baseUrl(config('services.groq.base_url'))
            ->timeout(60)
            ->acceptJson()
            ->post('/chat/completions', [
                'model' => config('services.groq.model'),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
                // Sem isso o modelo podia ignorar "até 3 parágrafos curtos" e
                // gerar uma resposta bem mais longa que o necessário — o
                // consumo de TPM da Groq soma tokens de entrada E de saída,
                // então uma resposta sem teto também contribui pra estourar
                // o limite. 700 tokens cobre com folga um resumo de até 3
                // parágrafos curtos mais o JSON ao redor.
                'max_tokens' => 700,
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
                            . 'Rejeite loteria, entretenimento, celebridades, fofoca, prêmios e indicações '
                            . 'artísticas (ex: Grammy, Oscar), shows e turnês de artistas, resultados esportivos '
                            . 'sem relevância pública, curiosidades, acidentes isolados sem relevância '
                            . 'institucional e qualquer assunto viral sem relação política ou social — mesmo que '
                            . 'a notícia apenas cite de passagem uma palavra como "governo": a relação precisa '
                            . 'ser central ao assunto da notícia, não incidental. O Votus cobre política '
                            . 'BRASILEIRA: rejeite notícias internacionais (eleições, política, entretenimento '
                            . 'ou justiça de outros países) que não tenham relação direta com o governo, a '
                            . 'diplomacia ou as políticas públicas do Brasil — um fato ocorrido fora do Brasil só '
                            . 'é relevante se a notícia for sobre como isso afeta o Brasil especificamente. '
                            . 'Pergunta-guia: essa notícia ajuda alguém a '
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

        return $this->parseResponse($texto);
    }

    private function parseResponse(string $texto): array
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
