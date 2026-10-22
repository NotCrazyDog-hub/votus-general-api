<?php

use App\Domains\Radar\Http\Controllers\RadarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Radar do Brasil
|--------------------------------------------------------------------------
| Temas das proposições por UF (Câmara e Senado) e indicadores do IBGE.
| Só lê resultados já calculados em segundo plano (ver RadarController).
*/

Route::middleware('cache.headers')->group(function () {
    Route::controller(RadarController::class)->prefix('radar')->group(function () {
        Route::get('/states', 'index');
        Route::get('/states/{uf}', 'show');
    });
});
