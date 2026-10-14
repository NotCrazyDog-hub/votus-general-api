
<?php

use Illuminate\Support\Facades\Route;

// Public Controllers
use App\Http\Controllers\{
    AiAssistantController,
    SchedulerController,
    SiteVisitController
};

// Admin Controllers
use App\Http\Controllers\Admin as Admin;

/*
|--------------------------------------------------------------------------
| Interactive Features, Telemetry & AI
|--------------------------------------------------------------------------
*/

Route::post('/ai-assistant/ask', [AiAssistantController::class, 'ask'])
    ->middleware('throttle:10,1')
    ->name('ai-assistant.ask');

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

require __DIR__.'/api/civic-participation.php';
require __DIR__.'/api/education.php';
require __DIR__.'/api/elections.php';
require __DIR__.'/api/executives.php';
require __DIR__.'/api/explanations.php';
require __DIR__.'/api/legislatures.php';
require __DIR__.'/api/news.php';
require __DIR__.'/api/opportunities.php';
