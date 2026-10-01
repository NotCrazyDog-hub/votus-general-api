<?php

namespace App\Console\Commands;

use App\Jobs\News\CollectAgenciaBrasilNewsJob;
use App\Jobs\News\CollectPoder360NewsJob;
use App\Models\NewsSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Signature('news:collect
    {--limit= : Teto de notícias novas gravadas POR FONTE, sobrescrevendo a divisão automática da meta do ciclo (usado pelo disparo manual do admin, que roda um lote menor)}
    {--force : Ignora a janela mínima entre coletas da fonte (offset_minutos). Usado pelo botão do admin, que deve rodar na hora, independente de quando foi a última execução automática}')]
#[Description('Despacha os Jobs de coleta de notícias para as fontes ativas e elegíveis no momento.')]
class CollectNews extends Command
{
    /**
     * Mapa de slug de fonte -> Job de coleta responsável por ela.
     * Cada fonte tem seu próprio Job isolado; uma falha em uma fonte não afeta as demais.
     */
    private const JOBS_POR_SLUG = [
        'agencia-brasil' => CollectAgenciaBrasilNewsJob::class,
        'poder360' => CollectPoder360NewsJob::class,
    ];

    /**
     * Meta de notícias NOVAS e VÁLIDAS por execução, somando as fontes.
     */
    private const META_POR_EXECUCAO = 15;

    public function handle(): int
    {
        // Evita despacho duplo quando cron e admin chegam no mesmo instante.
        // Os Jobs têm a própria trava por fonte (WithoutOverlapping) e o
        // índice único de link_normalizado segura qualquer corrida restante.
        $lock = Cache::lock('noticias:despacho-coleta', 30);

        if (!$lock->get()) {
            $this->warn('Outra coleta está sendo despachada neste momento; nada a fazer.');
            Log::info('[NEWS] Ciclo ignorado: outra coleta sendo despachada agora.');
            return self::SUCCESS;
        }

        try {
            return $this->dispatchJobs();
        } finally {
            $lock->release();
        }
    }

    private function dispatchJobs(): int
    {
        $forced = (bool) $this->option('force');
        $limitOption = $this->option('limit');

        Log::info('[NEWS] Ciclo iniciado' . ($forced ? ' (manual, forçado)' : ''));

        $sources = NewsSource::query()->where('ativa', true)->get()
            ->filter(fn (NewsSource $source) => $forced || $this->isEligible($source));

        if ($sources->isEmpty()) {
            $this->info('Nenhuma fonte ativa elegível neste momento.');
            Log::info('[NEWS] Ciclo finalizado: nenhuma fonte elegível.');
            return self::SUCCESS;
        }

        // Sem --limit, a meta do ciclo é repartida entre as fontes elegíveis
        // (ex.: 2 fontes -> até 8 novas cada, ~15 no total).
        $limit = $limitOption !== null
            ? max(1, (int) $limitOption)
            : (int) ceil(self::META_POR_EXECUCAO / $sources->count());

        foreach ($sources as $source) {
            $jobClass = self::JOBS_POR_SLUG[$source->slug] ?? null;

            if (!$jobClass) {
                $this->warn("Fonte '{$source->slug}' está ativa mas não possui Job de coleta registrado.");
                Log::warning("[NEWS] Fonte '{$source->slug}' ativa sem Job de coleta registrado.");
                continue;
            }

            $jobClass::dispatch($source->id, $limit);
            $this->info("Coleta despachada para '{$source->nome}' (até {$limit} novas).");
            Log::info("[NEWS] Coleta despachada: {$source->nome} (até {$limit} novas)");
        }

        return self::SUCCESS;
    }

    private function isEligible(NewsSource $source): bool
    {
        if (!$source->ultima_coleta_em) {
            return true;
        }

        return $source->ultima_coleta_em->addMinutes(max($source->offset_minutos, 1))->isPast();
    }
}
