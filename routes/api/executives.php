<?php

use App\Domains\Executives\Http\Controllers\ExecutiveController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Executives
|--------------------------------------------------------------------------
| Autoridades atualmente em exercício no Poder Executivo:
| Presidência da República e governos estaduais.
*/

Route::middleware('cache.headers')->group(function () {
    Route::controller(ExecutiveController::class)->group(function () {
        /*
        |--------------------------------------------------------------------------
        | Presidência da República
        |--------------------------------------------------------------------------
        */
        Route::get('/president', 'indexForPresident');
        Route::get('/president/{id}', 'showPresident');

        /*
        |--------------------------------------------------------------------------
        | Governadores
        |--------------------------------------------------------------------------
        */
        Route::get('/governors', 'indexForGovernors');
        Route::get('/governors/{id}', 'showGovernor');
    });
});