<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Limite conservador para não estourar o TPM (tokens por minuto) das
        // chaves da Groq quando muitas notícias são resumidas em sequência.
        // Baixado de 10 pra 5/min: o prompt do filtro de relevância do Votus
        // ficou mais longo (mais critérios explícitos de política brasileira
        // vs. internacional/entretenimento), então cada chamada consome mais
        // tokens — a 10/min isso estourava o limite de 8000 TPM da Groq e
        // fazia notícias boas caírem em "falhou" por rate limit, não por
        // serem realmente irrelevantes.
        RateLimiter::for('resumo-ia', function () {
            return Limit::perMinute(5);
        });
    }
}
