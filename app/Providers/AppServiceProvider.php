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
        RateLimiter::for('resumo-ia', function () {
            return Limit::perMinute(10);
        });
    }
}
