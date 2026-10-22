<?php

use Illuminate\Support\Facades\Route;

// Public Controllers
use App\Http\Controllers\{
    SchedulerController,
    SiteVisitController
};

// Admin Controllers
use App\Http\Controllers\Admin as Admin;

/*
|--------------------------------------------------------------------------
| Telemetry
|--------------------------------------------------------------------------
*/

Route::post('/site-visits', [SiteVisitController::class, 'store'])
    ->middleware('throttle:30,1');

/*
|--------------------------------------------------------------------------
| System Status
|--------------------------------------------------------------------------
*/

Route::get('/schedule/status', [SchedulerController::class, 'status']);

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {
    Route::post('/login', [Admin\AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        // Auth
        Route::post('/logout', [Admin\AuthController::class, 'logout']);
        Route::get('/me', [Admin\AuthController::class, 'me']);
        Route::get('/dashboard', [Admin\DashboardController::class, 'index']);
    });
});

/*
|--------------------------------------------------------------------------
| Domain Routes
|--------------------------------------------------------------------------
*/

foreach ([
    'ai-assistant',
    'civic-participation',
    'education',
    'elections',
    'executives',
    'explanations',
    'legislatures',
    'news',
    'opportunities',
    'radar',
] as $domain) {
    require __DIR__."/api/{$domain}.php";
}
