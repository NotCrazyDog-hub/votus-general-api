<?php

namespace App\Domains\Radar\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Domains\Radar\Services\IbgeSnapshot;
use App\Domains\Radar\Services\RadarRefresher;
use App\Domains\Radar\Services\RadarStore;
use App\Domains\Radar\Services\RadarUfs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Radar do Brasil. Só LÊ resultados já calculados e validados (cache do
 * Radar); nunca consulta as fontes oficiais durante a requisição. Se algo
 * está vencido ou ainda não existe, despacha a atualização em segundo plano
 * e responde com o que houver (ou status 'pending').
 *
 * Câmara, Senado e IBGE vêm em blocos separados e nunca são combinados.
 */
class RadarController extends Controller
{
    private const TOP_TOPICS_IN_OVERVIEW = 3;

    private const METHODOLOGY = [
        'what_it_measures' => 'Temas mais frequentes nas proposições (PL e PEC) apresentadas pelos parlamentares da UF. Mostra a atividade legislativa, não as prioridades da população do estado.',
        'camara' => 'Arquivos oficiais em lote da Câmara (proposicoesAutores e proposicoesTemas). Conta só deputados proponentes — assinaturas de apoio não contam. UF = UF do deputado autor. Cada proposição conta uma vez por UF, mesmo com vários autores da UF. Só entram proposições que já receberam tema oficial da Câmara.',
        'senado' => 'API de dados abertos do Senado. Senadores em exercício da UF; proposições apresentadas desde o início da 57ª legislatura. No Senado todos os signatários constam como autores. Classificação temática do próprio Senado (taxonomia diferente da Câmara). Amostras pequenas: só contagens absolutas.',
        'multiple_topics' => 'Uma proposição com vários temas conta em cada um deles; por isso os percentuais somam mais de 100%.',
        'periods' => 'Recorte anual = ano civil de apresentação (na Câmara, 2023 inclui janeiro, ainda na 56ª legislatura; no Senado, conta a partir de 01/02/2023). Legislatura = soma dos anos, sem duplicar proposições.',
        'ibge' => 'Indicadores oficiais do IBGE (API de agregados), cada um com seu ano de referência. Não são combinados com a atividade legislativa.',
    ];

    /** @var array<string, array> status de cada "fonte:escopo" lido nesta requisição */
    private array $statuses = [];

    public function __construct(private RadarStore $store, private RadarRefresher $refresher)
    {
    }

    /**
     * Resultados e status de todos os escopos da tela numa ida só ao cache.
     */
    private function read(array $pairs): array
    {
        $read = $this->store->readMany($pairs);
        $this->statuses = $read['statuses'];

        return $read['results'];
    }

    public function index(Request $request): JsonResponse
    {
        $period = $this->period($request);

        if ($period === null) {
            return $this->invalidPeriod();
        }

        $years = $this->yearsFor($period);
        $results = $this->read(array_merge(
            [['ibge', RadarStore::IBGE_SCOPE]],
            array_map(fn ($year) => ['camara', $year], $years),
        ));

        $ibge = $this->ibgeResult($results);
        $camaraByYear = $this->camaraYears($results, $years);
        $camara = $this->combineCamara($camaraByYear);

        $states = [];
        foreach (RadarUfs::ALL as $uf => $info) {
            $state = $camara['states'][$uf] ?? null;

            $states[] = $this->stateInfo($uf) + [
                'indicators' => $ibge === null ? null : array_map(
                    fn ($indicator) => $indicator['values'][$uf] ?? null,
                    $ibge['indicators'],
                ),
                'camara' => $camara === null ? null : [
                    'propositions' => $state['propositions'] ?? 0,
                    'top_topics' => array_slice($this->withShare($state), 0, self::TOP_TOPICS_IN_OVERVIEW),
                ],
            ];
        }

        return response()->json([
            'period' => $this->periodInfo($period),
            'methodology' => self::METHODOLOGY,
            'ibge' => $this->ibgeMeta($ibge),
            'camara' => $this->camaraMeta($camaraByYear, $years),
            'states' => $states,
        ]);
    }

    public function show(Request $request, string $uf): JsonResponse
    {
        $uf = strtoupper($uf);

        if (! RadarUfs::isValid($uf)) {
            return response()->json(['message' => 'UF não encontrada.'], 404);
        }

        $period = $this->period($request);

        if ($period === null) {
            return $this->invalidPeriod();
        }

        $years = $this->yearsFor($period);
        $results = $this->read(array_merge(
            [['ibge', RadarStore::IBGE_SCOPE], ['senado', $uf]],
            array_map(fn ($year) => ['camara', $year], $years),
        ));

        $ibge = $this->ibgeResult($results);
        $senado = $results["senado:{$uf}"];
        $camaraByYear = $this->camaraYears($results, $years);
        $camara = $this->combineCamara($camaraByYear);

        $this->refresher->dispatchIfStale('senado', $uf, $senado);

        $camaraState = $camara['states'][$uf] ?? null;

        return response()->json([
            'state' => $this->stateInfo($uf),
            'period' => $this->periodInfo($period),
            'methodology' => self::METHODOLOGY,
            'ibge' => $this->ibgeMeta($ibge) + [
                'values' => $ibge === null ? null : array_map(
                    fn ($indicator) => $indicator['values'][$uf] ?? null,
                    $ibge['indicators'],
                ),
            ],
            'camara' => $this->camaraMeta($camaraByYear, $years) + [
                'data' => $camara === null ? null : [
                    'propositions' => $camaraState['propositions'] ?? 0,
                    // Deputados distintos só fazem sentido dentro de um ano
                    // (somar anos contaria o mesmo deputado várias vezes).
                    'deputies' => count($years) === 1 ? ($camaraState['deputies'] ?? 0) : null,
                    'multi_theme_propositions' => $camaraState['multi_theme_propositions'] ?? 0,
                    'multi_author_same_uf_propositions' => $camaraState['multi_author_same_uf_propositions'] ?? 0,
                    'topics' => $this->withShare($camaraState),
                ],
            ],
            'senado' => $this->senadoBlock($senado, $uf, $period),
        ]);
    }

    private function period(Request $request): ?string
    {
        $period = (string) $request->query('period', 'legislature');

        if ($period === 'legislature' || in_array((int) $period, RadarRefresher::years(), true) && ctype_digit($period)) {
            return $period;
        }

        return null;
    }

    private function invalidPeriod(): JsonResponse
    {
        return response()->json([
            'message' => 'Período inválido. Use "legislature" ou um ano entre ' . implode(', ', RadarRefresher::years()) . '.',
        ], 422);
    }

    /**
     * @return int[]
     */
    private function yearsFor(string $period): array
    {
        return $period === 'legislature' ? RadarRefresher::years() : [(int) $period];
    }

    private function periodInfo(string $period): array
    {
        $years = RadarRefresher::years();

        return [
            'key' => $period,
            'label' => $period === 'legislature'
                ? '57ª legislatura (' . reset($years) . '–' . end($years) . ')'
                : "Ano de {$period}",
            'options' => array_merge(['legislature'], array_map('strval', $years)),
        ];
    }

    private function stateInfo(string $uf): array
    {
        $info = RadarUfs::ALL[$uf];

        return ['uf' => $uf, 'name' => $info['name'], 'region' => $info['region'], 'ibge_code' => $info['ibge_code']];
    }

    /**
     * Lê os resultados anuais da Câmara e despacha a atualização dos que
     * estiverem vencidos ou ausentes. Também cuida do IBGE.
     *
     * @return array<int, array|null>
     */
    private function camaraYears(array $results, array $years): array
    {
        $this->refresher->dispatchIfStale('ibge', RadarStore::IBGE_SCOPE, $this->ibgeResult($results));

        $byYear = [];
        foreach ($years as $year) {
            $byYear[$year] = $results["camara:{$year}"];
            $this->refresher->dispatchIfStale('camara', $year, $byYear[$year]);
        }

        return $byYear;
    }

    /**
     * Soma os anos pedidos. Cada proposição pertence a um único arquivo
     * anual, então a soma não duplica. Se faltar algum ano, devolve null:
     * um total parcial nunca é exibido como se fosse o da legislatura.
     */
    private function combineCamara(array $byYear): ?array
    {
        if (in_array(null, $byYear, true)) {
            return null;
        }

        if (count($byYear) === 1) {
            return reset($byYear);
        }

        $states = [];
        foreach ($byYear as $result) {
            foreach ($result['states'] as $uf => $state) {
                $target = &$states[$uf];
                $target['propositions'] = ($target['propositions'] ?? 0) + $state['propositions'];
                $target['multi_theme_propositions'] = ($target['multi_theme_propositions'] ?? 0) + $state['multi_theme_propositions'];
                $target['multi_author_same_uf_propositions'] = ($target['multi_author_same_uf_propositions'] ?? 0) + $state['multi_author_same_uf_propositions'];

                foreach ($state['topics'] as $topic) {
                    $target['topics'][$topic['code']] ??= ['code' => $topic['code'], 'name' => $topic['name'], 'count' => 0];
                    $target['topics'][$topic['code']]['count'] += $topic['count'];
                }
                unset($target);
            }
        }

        foreach ($states as &$state) {
            $state['topics'] = array_values($state['topics'] ?? []);
            usort($state['topics'], fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);
        }
        unset($state);

        return ['states' => $states];
    }

    private function withShare(?array $state): array
    {
        if ($state === null || $state['propositions'] === 0) {
            return [];
        }

        return array_map(fn ($topic) => $topic + [
            'share' => round($topic['count'] / $state['propositions'] * 100, 1),
        ], $state['topics']);
    }

    private function camaraMeta(array $byYear, array $years): array
    {
        $available = array_filter($byYear);
        $generated = array_map(fn ($r) => $r['generated_at'], $available);

        return [
            'status' => count($available) === count($years) ? 'ok' : 'pending',
            'missing_years' => array_values(array_keys(array_filter($byYear, fn ($r) => $r === null))),
            // A atualização mais antiga entre os anos usados.
            'generated_at' => $generated === [] ? null : min($generated),
            'last_failed_at' => $this->lastFailure('camara', $years, $available),
            'source_files' => array_values(array_merge(...array_map(
                fn ($r) => array_map(fn ($f) => ['url' => $f['url'], 'last_modified' => $f['last_modified']], array_values($r['files'] ?? [])),
                array_values($available),
            ))),
        ];
    }

    /**
     * Resultado publicado do IBGE; sem ele, a cópia dos valores oficiais do
     * repositório — a página nunca fica esperando uma consulta que pode não
     * funcionar do servidor.
     */
    private function ibgeResult(array $results): ?array
    {
        return $results['ibge:' . RadarStore::IBGE_SCOPE] ?? IbgeSnapshot::load();
    }

    private function ibgeMeta(?array $ibge): array
    {
        return [
            'status' => $ibge === null ? 'pending' : 'ok',
            'generated_at' => $ibge['generated_at'] ?? null,
            // true = valores oficiais guardados no repositório (ver IbgeSnapshot),
            // usados enquanto a atualização semanal não consegue consultar o IBGE.
            'from_snapshot' => (bool) ($ibge['snapshot'] ?? false),
            'last_failed_at' => $this->lastFailure('ibge', [RadarStore::IBGE_SCOPE], $ibge === null ? [] : [RadarStore::IBGE_SCOPE => $ibge]),
            'indicators' => $ibge === null ? null : array_map(fn ($i) => [
                'label' => $i['label'],
                'unit' => $i['unit'],
                'period' => $i['period'],
                'source' => $i['source'],
                'url' => $i['url'],
            ], $ibge['indicators']),
        ];
    }

    private function senadoBlock(?array $senado, string $uf, string $period): array
    {
        $meta = [
            'status' => $senado === null ? 'pending' : 'ok',
            'generated_at' => $senado['generated_at'] ?? null,
            'last_failed_at' => $this->lastFailure('senado', [$uf], $senado === null ? [] : [$uf => $senado]),
        ];

        if ($senado === null) {
            return $meta + ['data' => null];
        }

        $data = $period === 'legislature'
            ? $senado['legislature']
            : ($senado['years'][(int) $period] ?? ['propositions' => 0, 'without_topic' => 0, 'topics' => []]);

        return $meta + [
            'data' => $data + [
                'since' => $senado['since'],
                'senators' => $senado['senators'],
                'details_failed' => $senado['checks']['details_failed'],
                'small_sample' => $data['propositions'] < (int) config('radar.small_sample_threshold'),
            ],
        ];
    }

    /**
     * Data da última falha, só se for mais recente que o último sucesso — o
     * front avisa que está mostrando o último dado válido. A mensagem de
     * erro não é exposta.
     */
    private function lastFailure(string $source, array $scopes, array $available): ?string
    {
        $latest = null;

        foreach ($scopes as $scope) {
            $status = $this->statuses["{$source}:{$scope}"] ?? [];
            $failedAt = $status['last_error_at'] ?? null;
            $okAt = $available[$scope]['generated_at'] ?? null;

            if ($failedAt !== null && ($okAt === null || $failedAt > $okAt) && ($latest === null || $failedAt > $latest)) {
                $latest = $failedAt;
            }
        }

        return $latest;
    }
}
