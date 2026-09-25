<?php

namespace App\Console\Commands;

use App\Jobs\News\ColetarAgenciaBrasilNoticiasJob;
use App\Jobs\News\ColetarPoder360NoticiasJob;
use App\Models\Fonte;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

#[Signature('noticias:coletar
    {--limite= : Teto de notícias novas gravadas POR FONTE, sobrescrevendo a divisão automática da meta do ciclo (usado pelo disparo manual do admin, que roda um lote menor)}
    {--forcar : Ignora a janela mínima entre coletas da fonte (offset_minutos). Usado pelo botão do admin, que deve rodar na hora, independente de quando foi a última execução automática}')]
#[Description('Despacha os Jobs de coleta de notícias para as fontes ativas e elegíveis no momento.')]
class ColetarNoticias extends Command
{
    /**
     * Mapa de slug de fonte -> Job de coleta responsável por ela.
     * Cada fonte tem seu próprio Job isolado; uma falha em uma fonte não afeta as demais.
     */
    private const JOBS_POR_SLUG = [
        'agencia-brasil' => ColetarAgenciaBrasilNoticiasJob::class,
        'poder360' => ColetarPoder360NoticiasJob::class,
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
        $trava = Cache::lock('noticias:despacho-coleta', 30);

        if (!$trava->get()) {
            $this->warn('Outra coleta está sendo despachada neste momento; nada a fazer.');
            Log::info('[NEWS] Ciclo ignorado: outra coleta sendo despachada agora.');
            return self::SUCCESS;
        }

        try {
            return $this->despachar();
        } finally {
            $trava->release();
        }
    }

    private function despachar(): int
    {
        $forcar = (bool) $this->option('forcar');
        $limiteOption = $this->option('limite');

        Log::info('[NEWS] Ciclo iniciado' . ($forcar ? ' (manual, forçado)' : ''));

        $fontes = Fonte::query()->where('ativa', true)->get()
            ->filter(fn (Fonte $fonte) => $forcar || $this->elegivel($fonte));

        if ($fontes->isEmpty()) {
            $this->info('Nenhuma fonte ativa elegível neste momento.');
            Log::info('[NEWS] Ciclo finalizado: nenhuma fonte elegível.');
            return self::SUCCESS;
        }

        // Sem --limite, a meta do ciclo é repartida entre as fontes elegíveis
        // (ex.: 2 fontes -> até 8 novas cada, ~15 no total).
        $limite = $limiteOption !== null
            ? max(1, (int) $limiteOption)
            : (int) ceil(self::META_POR_EXECUCAO / $fontes->count());

        foreach ($fontes as $fonte) {
            $jobClass = self::JOBS_POR_SLUG[$fonte->slug] ?? null;

            if (!$jobClass) {
                $this->warn("Fonte '{$fonte->slug}' está ativa mas não possui Job de coleta registrado.");
                Log::warning("[NEWS] Fonte '{$fonte->slug}' ativa sem Job de coleta registrado.");
                continue;
            }

            $jobClass::dispatch($fonte->id, $limite);
            $this->info("Coleta despachada para '{$fonte->nome}' (até {$limite} novas).");
            Log::info("[NEWS] Coleta despachada: {$fonte->nome} (até {$limite} novas)");
        }

        return self::SUCCESS;
    }

    private function elegivel(Fonte $fonte): bool
    {
        if (!$fonte->ultima_coleta_em) {
            return true;
        }

        return $fonte->ultima_coleta_em->addMinutes(max($fonte->offset_minutos, 1))->isPast();
    }
}
