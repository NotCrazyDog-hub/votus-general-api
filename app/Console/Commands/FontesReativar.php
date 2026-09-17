<?php

namespace App\Console\Commands;

use App\Models\Fonte;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fontes:reativar {slug : Slug da fonte a reativar (ex: agencia-brasil)}')]
#[Description('Reativa manualmente uma fonte desativada pelo circuit breaker, zerando o contador de falhas.')]
class FontesReativar extends Command
{
    public function handle(): int
    {
        $slug = $this->argument('slug');
        $fonte = Fonte::where('slug', $slug)->first();

        if (!$fonte) {
            $this->error("Fonte '{$slug}' não encontrada.");

            return self::FAILURE;
        }

        $fonte->reativar();

        $this->info("Fonte '{$fonte->nome}' reativada. Falhas consecutivas zeradas.");

        return self::SUCCESS;
    }
}
