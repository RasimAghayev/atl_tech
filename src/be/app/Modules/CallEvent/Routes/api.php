<?php

declare(strict_types=1);

use App\Modules\CallEvent\Http\Controllers\CallEventController;
use App\Modules\CallEvent\Http\Middleware\VerifyApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware([VerifyApiToken::class, 'throttle:60,1'])
    ->prefix('api/v1')
    ->group(function () {
        Route::post('call-events', [CallEventController::class, 'store'])
            ->name('call-events.store');
    });