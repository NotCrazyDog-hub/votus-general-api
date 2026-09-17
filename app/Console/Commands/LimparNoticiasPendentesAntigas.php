<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('noticias:limpar-pendentes-antigas')]
#[Description('Remove notícias cujo resumo não terminou (ou falhou) em até 8h — normalmente sinal de que o cron externo de resumo não rodou a tempo, ou de erro definitivo (ex: rate limit da IA).')]
class LimparNoticiasPendentesAntigas extends Command
{
    private const HORAS_LIMITE = 8;

    public function handle(): int
    {
        // Inclui 'falhou' também: notícia que deu erro (rate limit da IA,
        // fonte fora do ar etc.) e ficou velha não deve poluir o painel pra
        // sempre — o admin ainda pode remover manualmente antes disso pelo
        // botão de excluir.
        $removidas = News::whereIn('status_resumo', ['pendente', 'em_processamento', 'falhou'])
            ->where('imported_at', '<', now()->subHours(self::HORAS_LIMITE))
            ->delete();

        if ($removidas > 0) {
            $this->info("{$removidas} notícia(s) pendente(s)/falha(s) há mais de " . self::HORAS_LIMITE . "h removida(s).");
        }

        return self::SUCCESS;
    }
}
