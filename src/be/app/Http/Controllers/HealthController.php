<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * /health/live and /health/ready — distinct on purpose (standing instruction:
 * readiness is scoped to only what the process truly needs synchronously).
 *
 * Liveness: "is the PHP process alive and able to answer HTTP at all". No
 * dependency check — a dependency outage must never cause an orchestrator to
 * kill and restart otherwise-healthy app instances.
 *
 * Readiness: "can this instance currently serve real traffic". Checks
 * PostgreSQL only — the request path's one synchronous dependency. RabbitMQ
 * is deliberately NOT checked here: thanks to the transactional outbox
 * (ADR-0001), the API never talks to RabbitMQ synchronously at all anymore.
 * A broker outage grows the outbox_events backlog; it does not make this
 * instance unable to accept call events, so it must not flip readiness.
 */
final class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');
        } catch (\Throwable) {
            return response()->json(['status' => 'unavailable', 'reason' => 'database unreachable'], 503);
        }

        return response()->json(['status' => 'ok']);
    }
}
