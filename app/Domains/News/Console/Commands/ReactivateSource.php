<?php

namespace App\Domains\News\Console\Commands;

use App\Domains\News\Models\NewsSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sources:reactivate {slug : Slug da fonte a reativar (ex: agencia-brasil)}')]
#[Description('Reativa manualmente uma fonte desativada pelo circuit breaker, zerando o contador de falhas.')]
class ReactivateSource extends Command
{
    public function handle(): int
    {
        $slug = $this->argument('slug');
        $source = NewsSource::where('slug', $slug)->first();

        if (!$source) {
            $this->error("Fonte '{$slug}' não encontrada.");

            return self::FAILURE;
        }

        $source->reactivate();

        $this->info("Fonte '{$source->nome}' reativada. Falhas consecutivas zeradas.");

        return self::SUCCESS;
    }
}
