<?php

namespace App\Domains\Radar\Services;

use App\Domains\Legislatures\Services\Senate\SenateApiService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Temas das proposições dos senadores EM EXERCÍCIO de uma UF, usando só os
 * métodos já existentes do SenateApiService (sem alterá-los):
 *  - listParliamentarians($uf): os senadores em exercício da UF;
 *  - getBillsByLegislator($id): PL e PEC em que o senador é autor;
 *  - getTopics($id): a classificação oficial do Senado (1 chamada por
 *    proposição — a classificação só vem no detalhe).
 *
 * Regra: proposições apresentadas desde o início da 57ª legislatura; cada
 * proposição conta uma vez na UF, mesmo com vários senadores da UF entre os
 * autores. No Senado todos os signatários de uma PEC constam como autores
 * (não há distinção proponente/apoio como na Câmara). As classificações do
 * Senado são outra taxonomia: nunca somadas às da Câmara.
 */
class SenadoRadarCollector
{
    // Limite de tempo para novas tentativas nesta coleta (o job tem 30 min).
    private float $deadline = 0;

    public function __construct(private SenateApiService $api)
    {
    }

    /**
     * Coleta senadores, proposições e temas para uma UF. Deduplica projetos
     * assinados por mais de um senador, agrupa por ano e mantém em checks os
     * detalhes que não puderam ser consultados após as tentativas.
     */
    public function collect(string $uf): array
    {
        $uf = strtoupper($uf);
        $since = config('radar.legislature.start');
        $types = array_flip(config('radar.bill_types'));
        $sleepUs = (int) config('radar.senado.sleep_ms', 1000) * 1000;
        $this->deadline = microtime(true) + (int) config('radar.senado.retry_budget_seconds', 1200);

        // Falha aqui lança exceção: sem a lista não dá para saber o universo.
        $senators = $this->withRetry(fn () => $this->api->listParliamentarians($uf));

        if ($senators === []) {
            throw new RuntimeException("Senado não retornou senadores em exercício para {$uf}");
        }

        $bills = [];
        $senatorList = [];

        foreach ($senators as $senator) {
            $id = (string) ($senator['IdentificacaoParlamentar']['CodigoParlamentar'] ?? '');

            if ($id === '') {
                throw new RuntimeException("Senador sem código na lista do Senado ({$uf})");
            }

            $senatorList[] = [
                'code' => $id,
                'name' => $senator['IdentificacaoParlamentar']['NomeParlamentar'] ?? null,
                'participation' => $senator['Mandato']['DescricaoParticipacao'] ?? null,
            ];

            foreach ($this->withRetry(fn () => $this->api->getBillsByLegislator($id)) as $bill) {
                $date = (string) ($bill['dataApresentacao'] ?? '');
                $type = strtok((string) ($bill['identificacao'] ?? ''), ' ');

                if ($date === '' || $date < $since || ! isset($types[$type]) || empty($bill['id'])) {
                    continue;
                }

                $bills[(string) $bill['id']] = [
                    'year' => (int) substr($date, 0, 4),
                    'identification' => $bill['identificacao'],
                ];
            }

            usleep($sleepUs);
        }

        $failed = [];
        $billTopics = [];

        foreach ($bills as $billId => $info) {
            $topics = $this->topicsOrNull($billId);

            if ($topics === null) {
                $failed[] = $info['identification'];
            } else {
                $billTopics[$billId] = $topics;
            }

            usleep($sleepUs);
        }

        $years = [];
        foreach ($billTopics as $billId => $topics) {
            $years[$bills[$billId]['year']][$billId] = $topics;
        }
        ksort($years);

        return [
            'uf' => $uf,
            'since' => $since,
            'senators' => $senatorList,
            'checks' => [
                'bills_found' => count($bills),
                'bills_classified_fetch_ok' => count($billTopics),
                'details_failed' => count($failed),
                'details_failed_bills' => array_slice($failed, 0, 20),
            ],
            'years' => array_map(fn ($yearBills) => $this->summarize($yearBills), $years),
            'legislature' => $this->summarize($billTopics),
        ];
    }

    /**
     * @param  array<string, array>  $billTopics  id => temas (getTopics)
     */
    private function summarize(array $billTopics): array
    {
        $counts = [];
        $withoutTopic = 0;

        foreach ($billTopics as $topics) {
            if ($topics === []) {
                $withoutTopic++;
            }

            foreach ($topics as $topic) {
                $counts[$topic['name']] = ($counts[$topic['name']] ?? 0) + 1;
            }
        }

        $list = [];
        foreach ($counts as $name => $count) {
            $list[] = ['name' => (string) $name, 'count' => $count];
        }

        usort($list, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return [
            'propositions' => count($billTopics),
            'without_topic' => $withoutTopic,
            'topics' => $list,
        ];
    }

    /**
     * Classificação de uma proposição; null se não der para ler mesmo depois
     * das novas tentativas (a proposição fica fora da contagem e entra em
     * details_failed, como antes).
     */
    private function topicsOrNull(string $billId): ?array
    {
        try {
            return $this->withRetry(fn () => $this->api->getTopics($billId));
        } catch (Throwable $e) {
            Log::info("[RADAR] Senado: classificação de {$billId} não pôde ser lida: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Repete a chamada em falhas transitórias do Senado, com espera crescente.
     * As medidas: 503, TLS e conexão recusada (somem em segundos) e 429 "Too
     * Many Requests" (o Senado limita o volume de requisições, sem dizer por
     * quanto tempo — por isso a espera é bem maior). Outros erros, ou o fim do
     * tempo da coleta, devolvem a exceção na hora.
     */
    private function withRetry(\Closure $call): mixed
    {
        $attempt = 0;

        while (true) {
            try {
                return $call();
            } catch (Throwable $e) {
                $tooMany = str_ends_with($e->getMessage(), ': 429');
                $transient = $tooMany
                    || preg_match('/: 5\d\d$/', $e->getMessage())
                    || $e instanceof \Illuminate\Http\Client\ConnectionException;
                $waits = config($tooMany ? 'radar.senado.backoff_429_seconds' : 'radar.senado.backoff_seconds', []);
                $wait = $waits[$attempt] ?? null;

                if (! $transient || $wait === null || microtime(true) + $wait > $this->deadline) {
                    throw $e;
                }

                $attempt++;
                Log::info("[RADAR] Senado: {$e->getMessage()} — nova tentativa {$attempt} em {$wait}s");
                usleep((int) ($wait * 1_000_000));
            }
        }
    }
}
