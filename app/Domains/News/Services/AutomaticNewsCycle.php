<?php

namespace App\Domains\News\Services;

use App\Domains\News\Models\NewsSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Garantia do ciclo automático de 12h, independente do botão do admin.
 *
 * O disparo principal continua sendo o cron externo (cron-job.org chamando
 * /api/schedule/coletar-noticias). Mas no banco a última coleta era de
 * 24/09 10:50 e antes disso 18/09 — o ciclo de 12h não estava acontecendo.
 * Como o Render free não tem cron nativo, isto é a rede de segurança: se já
 * passou do prazo, a primeira leitura pública de notícias dispara o mesmo
 * comando do cron (só despacha os Jobs; quem processa é o worker de fila do
 * container, ver entrypoint.sh). Não atrasa a resposta de forma relevante:
 * a checagem fica em cache local por alguns minutos.
 */
class AutomaticNewsCycle
{
    public const INTERVALO_HORAS = 12;

    // Com que frequência, no máximo, consultar o banco pra saber se venceu.
    private const CHECAGEM_MINUTOS = 10;

    public function triggerIfDue(): void
    {
        try {
            $due = Cache::store('file')->remember(
                'noticias:ciclo-vencido',
                now()->addMinutes(self::CHECAGEM_MINUTOS),
                fn () => $this->isDue(),
            );

            if (!$due) {
                return;
            }

            // Uma única requisição dispara; as demais no mesmo intervalo não.
            if (!Cache::lock('noticias:ciclo-automatico', 300)->get()) {
                return;
            }

            // Depois de disparar, espera 1h antes de reconsiderar: os Jobs levam
            // alguns minutos pra rodar e só então atualizam ultima_coleta_em —
            // sem isso, cada checagem de 10 min despacharia outra leva.
            Cache::store('file')->put('noticias:ciclo-vencido', false, now()->addHour());

            Log::info('[NEWS] Ciclo automático vencido (>' . self::INTERVALO_HORAS . 'h sem coleta): disparando.');
            Artisan::call('news:collect');
        } catch (Throwable $e) {
            // Nunca derruba a listagem pública por causa disso.
            Log::error("[NEWS] Falha ao disparar ciclo automático: {$e->getMessage()}");
        }
    }

    private function isDue(): bool
    {
        $lastCollectedAt = NewsSource::query()->where('ativa', true)->max('ultima_coleta_em');

        return $lastCollectedAt === null
            || now()->diffInHours(Carbon::parse($lastCollectedAt), true) >= self::INTERVALO_HORAS;
    }
}
