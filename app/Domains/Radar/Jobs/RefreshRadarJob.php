<?php

namespace App\Domains\Radar\Jobs;

use App\Domains\Radar\Services\RadarRefresher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Atualiza um escopo do Radar em segundo plano (fila 'radar', processada
 * pelo mesmo worker do entrypoint.sh, depois de 'coleta' e 'resumo').
 *
 * Toda a lógica (lock, validação, publicação, registro de erro) está no
 * RadarRefresher, que nunca deixa exceção escapar: uma falha do Radar não
 * derruba o worker nem afeta as filas de notícias.
 */
class RefreshRadarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Download dos arquivos da Câmara pode levar vários minutos (o servidor
    // derruba a conexão e o download é retomado).
    public const TIMEOUT = 1800;

    public int $timeout = self::TIMEOUT;

    public int $tries = 1;

    public function __construct(public string $source, public string|int $scope)
    {
        $this->onQueue(config('radar.queue'));
    }

    public function handle(RadarRefresher $refresher): void
    {
        $refresher->refresh($this->source, $this->scope);
    }
}
