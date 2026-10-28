<?php

namespace App\Domains\Radar\Services;

use App\Domains\Radar\Jobs\RefreshRadarJob;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Ciclo de atualização do Radar:
 *  1. trava (lock) por fonte+escopo — uma execução por vez;
 *  2. coleta nas fontes oficiais (tudo em memória/pasta temporária);
 *  3. valida;
 *  4. só então publica, e só se este cálculo for mais novo que o publicado.
 * Em qualquer falha o resultado anterior continua no ar e o erro vai para o
 * status (RadarStore::recordFailure). Nada aqui apaga dado.
 */
class RadarRefresher
{
    // Folga do lock acima do timeout do Job, para não liberar no meio.
    private const LOCK_SECONDS = RefreshRadarJob::TIMEOUT + 120;

    // Após despachar, não despacha de novo o mesmo escopo por este tempo.
    // IBGE: o servidor às vezes não consegue abrir conexão com o IBGE, então
    // uma tentativa por dia no máximo (a atualização é semanal; ver
    // radar.stale_after_hours.ibge) em vez de uma por hora.
    private const DISPATCH_COOLDOWN_MINUTES = ['ibge' => 24 * 60, 'senado' => 6 * 60, 'camara' => 60];

    public function __construct(
        private RadarStore $store,
        private CamaraRadarCollector $camara,
        private SenadoRadarCollector $senado,
        private IbgeIndicatorsCollector $ibge,
    ) {
    }

    /**
     * Coleta e valida SEM gravar nada (usado pelo --dry-run e pelo refresh).
     */
    public function compute(string $source, string|int $scope): array
    {
        $scope = $this->normalizeScope($source, $scope);
        $startedAtMs = (int) floor(microtime(true) * 1000);

        $data = match ($source) {
            'camara' => $this->camara->collect($scope),
            'senado' => $this->senado->collect($scope),
            'ibge' => $this->ibge->collect(),
        };

        $result = [
            'source' => $source,
            'scope' => $scope,
            'started_at_ms' => $startedAtMs,
            'generated_at' => now()->toIso8601String(),
        ] + $data;

        $this->validate($source, $scope, $result);

        return $result;
    }

    /**
     * @return string 'published' | 'skipped:fresh' | 'skipped:locked' | 'skipped:older' | 'failed'
     */
    public function refresh(string $source, string|int $scope, bool $force = false): string
    {
        $scope = $this->normalizeScope($source, $scope);
        $lock = $this->store->refreshLock($source, $scope, self::LOCK_SECONDS);

        if (! $lock->get()) {
            Log::info("[RADAR] {$source}:{$scope} já está sendo atualizado; ignorando.");

            return 'skipped:locked';
        }

        try {
            if (! $force && ! $this->isStale($source, $scope, $this->store->result($source, $scope))) {
                return 'skipped:fresh';
            }

            $this->store->recordStatus($source, $scope, ['last_attempt_at' => now()->toIso8601String()]);

            $result = $this->compute($source, $scope);

            if (! $this->store->publish($source, $scope, $result)) {
                Log::info("[RADAR] {$source}:{$scope}: já existe resultado mais novo publicado; descartado.");

                return 'skipped:older';
            }

            Log::info("[RADAR] {$source}:{$scope} publicado.");

            return 'published';
        } catch (Throwable $e) {
            Log::warning("[RADAR] {$source}:{$scope} falhou, resultado anterior mantido: {$e->getMessage()}");
            $this->store->recordFailure($source, $scope, mb_substr($e->getMessage(), 0, 500));

            return 'failed';
        } finally {
            $lock->release();
        }
    }

    public function isStale(string $source, string|int $scope, ?array $result): bool
    {
        if ($result === null || empty($result['generated_at'])) {
            return true;
        }

        $hours = match (true) {
            $source === 'ibge' => config('radar.stale_after_hours.ibge'),
            $source === 'senado' => config('radar.stale_after_hours.senado'),
            (int) $scope >= now()->year => config('radar.stale_after_hours.current_year'),
            default => config('radar.stale_after_hours.past_years'),
        };

        return now()->diffInHours($result['generated_at'], true) >= $hours;
    }

    /**
     * Chamado na leitura pública: se vencido, despacha o Job em segundo plano
     * (fila radar) e volta na hora. Nunca lança exceção para a requisição.
     */
    public function dispatchIfStale(string $source, string|int $scope, ?array $result): void
    {
        try {
            if (! $this->isStale($source, $scope, $result)) {
                return;
            }

            if (! $this->store->claimDispatch($source, $scope, self::DISPATCH_COOLDOWN_MINUTES[$source] ?? 60)) {
                return;
            }

            RefreshRadarJob::dispatch($source, $this->normalizeScope($source, $scope));
        } catch (Throwable $e) {
            Log::error("[RADAR] Falha ao despachar atualização {$source}:{$scope}: {$e->getMessage()}");
        }
    }

    /**
     * @return int[]
     */
    public static function years(): array
    {
        $first = (int) config('radar.legislature.first_year');
        $last = min((int) config('radar.legislature.last_year'), (int) now()->year);

        return range($first, $last);
    }

    public function normalizeScope(string $source, string|int $scope): string|int
    {
        return match ($source) {
            'camara' => in_array((int) $scope, self::years(), true)
                ? (int) $scope
                : throw new InvalidArgumentException("Ano fora da legislatura: {$scope}"),
            'senado' => RadarUfs::isValid((string) $scope)
                ? strtoupper((string) $scope)
                : throw new InvalidArgumentException("UF inválida: {$scope}"),
            'ibge' => RadarStore::IBGE_SCOPE,
            default => throw new InvalidArgumentException("Fonte inválida: {$source}"),
        };
    }

    private function validate(string $source, string|int $scope, array $result): void
    {
        match ($source) {
            'camara' => $this->validateCamara($scope, $result),
            'senado' => $this->validateSenado($result),
            'ibge' => $this->validateIbge($result),
        };
    }

    private function validateCamara(int $year, array $result): void
    {
        $total = $result['totals']['bills_with_deputy_author'] ?? 0;
        $checks = $result['checks'];

        if ($total <= 0 || $result['states'] === []) {
            throw new RuntimeException("Câmara {$year}: nenhuma proposição de deputado encontrada");
        }

        foreach (array_keys($result['states']) as $uf) {
            if (! RadarUfs::isValid($uf)) {
                throw new RuntimeException("Câmara {$year}: UF desconhecida {$uf}");
            }
        }

        // Linhas de deputado sem UF são esperadas mas raras (medido: 1 em
        // 8.879 em 2025). Muitas indicariam arquivo com estrutura mudada.
        $considered = $checks['author_rows_used'] + $checks['author_rows_without_uf'];
        if ($considered > 0 && $checks['author_rows_without_uf'] / $considered > 0.02) {
            throw new RuntimeException("Câmara {$year}: proporção alta de autores sem UF");
        }

        $previous = $this->store->result('camara', $year);
        $ratio = (float) config('radar.camara.min_ratio_vs_previous');
        $previousTotal = $previous['totals']['bills_with_deputy_author'] ?? 0;

        if ($previousTotal > 0 && $total < $previousTotal * $ratio) {
            throw new RuntimeException("Câmara {$year}: {$total} proposições contra {$previousTotal} publicadas — possível arquivo incompleto");
        }
    }

    private function validateSenado(array $result): void
    {
        $checks = $result['checks'];

        if ($result['senators'] === []) {
            throw new RuntimeException('Senado: nenhum senador');
        }

        // Falhas pontuais de detalhe são toleradas até 5% e ficam expostas
        // (details_failed) na resposta; acima disso o resultado é recusado.
        if ($checks['bills_found'] > 0 && $checks['details_failed'] / $checks['bills_found'] > 0.05) {
            throw new RuntimeException("Senado {$result['uf']}: {$checks['details_failed']} de {$checks['bills_found']} classificações não puderam ser lidas");
        }
    }

    private function validateIbge(array $result): void
    {
        foreach ($result['indicators'] as $key => $indicator) {
            if (count($indicator['values']) !== count(RadarUfs::ALL)) {
                throw new RuntimeException("IBGE {$key}: " . count($indicator['values']) . ' UFs, esperado 27');
            }

            foreach ($indicator['values'] as $uf => $value) {
                if ($value <= 0) {
                    throw new RuntimeException("IBGE {$key}: valor não positivo para {$uf}");
                }
            }
        }
    }
}
