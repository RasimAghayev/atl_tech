<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    return view('welcome');
});

// Unauthenticated on purpose — these are load-balancer/orchestrator probes,
// not application API. See HealthController for why liveness and readiness
// check different things.
Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
