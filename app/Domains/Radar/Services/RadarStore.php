<?php

namespace App\Domains\Radar\Services;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Único ponto de leitura/escrita do Radar no cache.
 *
 * Usa o store config('radar.cache_store') = 'database', ou seja, a tabela
 * `cache` que já existe no Supabase (sobrevive a deploy e a reinício do
 * Render, ao contrário do store 'file'). Toda chave leva o
 * prefixo config('radar.cache_prefix'), e o Radar nunca chama flush/clear:
 * só grava e lê as próprias chaves.
 *
 * Chaves:
 *  - result:{fonte}:{escopo}  último resultado VALIDADO (o que a API serve)
 *  - status:{fonte}:{escopo}  última tentativa, último sucesso, último erro
 *  - lock:{fonte}:{escopo}    trava de execução (uma atualização por vez)
 *  - publish                  trava curta da troca do resultado publicado
 *
 * Resultado e status nunca expiram sozinhos (forever): um resultado só é
 * substituído por outro mais novo e validado — nunca por erro ou vazio.
 */
class RadarStore
{
    public const SOURCES = ['camara', 'senado', 'ibge'];

    // Escopo único da fonte IBGE (os indicadores não são por ano legislativo).
    public const IBGE_SCOPE = 'all';

    public function __construct(private ?Repository $cache = null)
    {
    }

    public function key(string $suffix): string
    {
        return config('radar.cache_prefix') . $suffix;
    }

    public function result(string $source, string|int $scope): ?array
    {
        $value = $this->cache()->get($this->key("result:{$source}:{$scope}"));

        return is_array($value) ? $value : null;
    }

    /**
     * Lê resultados e status de vários escopos numa ida só ao cache (no
     * driver 'database' vira um único SELECT ... WHERE key IN (...)).
     *
     * @param  array<int, array{0: string, 1: string|int}>  $pairs
     * @return array{results: array<string, array|null>, statuses: array<string, array>}  indexados por "fonte:escopo"
     */
    public function readMany(array $pairs): array
    {
        $keys = [];
        foreach ($pairs as [$source, $scope]) {
            $keys["{$source}:{$scope}"] = [$this->key("result:{$source}:{$scope}"), $this->key("status:{$source}:{$scope}")];
        }

        $values = $this->cache()->many(array_merge(...array_values($keys)));

        $out = ['results' => [], 'statuses' => []];
        foreach ($keys as $id => [$resultKey, $statusKey]) {
            $out['results'][$id] = is_array($values[$resultKey] ?? null) ? $values[$resultKey] : null;
            $out['statuses'][$id] = is_array($values[$statusKey] ?? null) ? $values[$statusKey] : [];
        }

        return $out;
    }

    public function status(string $source, string|int $scope): array
    {
        $value = $this->cache()->get($this->key("status:{$source}:{$scope}"));

        return is_array($value) ? $value : [];
    }

    /**
     * Publica um resultado já validado. Só substitui o publicado se este
     * cálculo começou DEPOIS do que está no ar — assim duas execuções que
     * terminem fora de ordem nunca trocam um dado novo por um mais antigo.
     */
    public function publish(string $source, string|int $scope, array $result): bool
    {
        $lock = $this->cache()->lock($this->key('publish'), 30);

        return (bool) $lock->block(10, function () use ($source, $scope, $result) {
            $current = $this->result($source, $scope);

            if ($current !== null && ($current['started_at_ms'] ?? 0) > ($result['started_at_ms'] ?? 0)) {
                return false;
            }

            $this->cache()->forever($this->key("result:{$source}:{$scope}"), $result);
            $this->recordStatus($source, $scope, [
                'last_success_at' => $result['generated_at'] ?? now()->toIso8601String(),
                'last_error' => null,
            ]);

            return true;
        });
    }

    /**
     * Registra tentativa/erro sem nunca tocar no resultado publicado.
     */
    public function recordStatus(string $source, string|int $scope, array $fields): void
    {
        $status = array_merge($this->status($source, $scope), $fields);
        $this->cache()->forever($this->key("status:{$source}:{$scope}"), $status);
    }

    public function recordFailure(string $source, string|int $scope, string $message): void
    {
        $this->recordStatus($source, $scope, [
            'last_error' => $message,
            'last_error_at' => now()->toIso8601String(),
        ]);
    }

    public function refreshLock(string $source, string|int $scope, int $seconds)
    {
        return $this->cache()->lock($this->key("lock:{$source}:{$scope}"), $seconds);
    }

    /**
     * Garante que uma mesma atualização não seja despachada várias vezes
     * seguidas por requisições simultâneas (Cache::add é atômico).
     */
    public function claimDispatch(string $source, string|int $scope, int $minutes): bool
    {
        return $this->cache()->add($this->key("dispatched:{$source}:{$scope}"), true, now()->addMinutes($minutes));
    }

    private function cache(): Repository
    {
        return $this->cache ?? Cache::store(config('radar.cache_store', 'database'));
    }
}
