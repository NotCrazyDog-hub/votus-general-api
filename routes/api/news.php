<?php

use App\Domains\News\Http\Controllers\{
    NewsController,
    NewsPipelineController
};
use App\Domains\News\Http\Controllers\Admin\NewsController as AdminNewsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| News Pipeline
|--------------------------------------------------------------------------
*/

Route::prefix('schedule')
    ->controller(NewsPipelineController::class)
    ->middleware('throttle:6,1')
    ->group(function () {
        Route::post('/coletar-noticias', 'runNewsPipeline');
        Route::post('/processar-fila-noticias', 'processNewsQueue');
    });

/*
|--------------------------------------------------------------------------
| News - público e interno
|--------------------------------------------------------------------------
*/

Route::middleware('cache.headers')->group(function () {
    Route::controller(NewsController::class)
        ->prefix('news')
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/{news}', 'show');
            Route::post('/', 'store')->middleware('internal.token');
        });
});

/*
|--------------------------------------------------------------------------
| News - administração
|--------------------------------------------------------------------------
*/

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function () {
        Route::controller(AdminNewsController::class)
            ->prefix('news')
            ->group(function () {
                Route::get('/', 'index');
                Route::post('/collect', 'collect')->middleware('throttle:6,1');
                Route::post('/drain', 'drain')->middleware('throttle:20,1');
                Route::delete('/{id}', 'destroy');
            });
    });