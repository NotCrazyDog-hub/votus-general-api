<?php

namespace App\Console\Commands;

use App\Jobs\News\ColetarAgenciaBrasilNoticiasJob;
use App\Jobs\News\ColetarPoder360NoticiasJob;
use App\Models\Fonte;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('noticias:coletar')]
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

    public function handle(): int
    {
        $fontes = Fonte::query()->where('ativa', true)->get();

        foreach ($fontes as $fonte) {
            if (!$this->elegivel($fonte)) {
                continue;
            }

            $jobClass = self::JOBS_POR_SLUG[$fonte->slug] ?? null;

            if (!$jobClass) {
                $this->warn("Fonte '{$fonte->slug}' está ativa mas não possui Job de coleta registrado.");
                continue;
            }

            $jobClass::dispatch($fonte->id);
            $this->info("Coleta despachada para '{$fonte->nome}'.");
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
