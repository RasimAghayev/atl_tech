<?php

declare(strict_types=1);

use App\Modules\CallEvent\Http\Controllers\CallEventController;
use App\Modules\CallEvent\Http\Middleware\VerifyApiToken;
use Illuminate\Support\Facades\Route;

// NOTE: module routes are registered via loadRoutesFrom() in CallEventServiceProvider,
// not through the file passed to withRouting(api: ...) — so the global "api" middleware
// group declared in bootstrap/app.php is never actually applied to these routes. Each
// route below therefore declares its own throttle explicitly; do not remove either
// under the assumption the global group already covers it (it doesn't).
Route::middleware([VerifyApiToken::class])
    ->prefix('api/v1')
    ->group(function () {
        Route::post('call-events', [CallEventController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('call-events.store');

        Route::get('call-events/{call_id}', [CallEventController::class, 'history'])
            ->middleware('throttle:120,1')
            ->name('call-events.history');
    });
