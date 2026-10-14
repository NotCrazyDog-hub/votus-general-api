<?php

use App\Domains\AiAssistant\Http\Controllers\AiAssistantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Assistant
|--------------------------------------------------------------------------
*/

Route::post('/ai-assistant/ask', [AiAssistantController::class, 'ask'])
    ->middleware('throttle:10,1')
    ->name('ai-assistant.ask');
