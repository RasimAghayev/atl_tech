<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Http\Controllers;

use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Http\Requests\CallEventRequest;
use App\Modules\CallEvent\Models\CallEventLog;
use App\Modules\CallEvent\Services\CallEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'CallEvent', description: 'Call event management from SIP server')]
class CallEventController extends Controller
{
    public function __construct(
        private readonly CallEventService $service
    ) {
    }

    #[OA\Post(
        path: '/api/v1/call-events',
        summary: 'Receive call event from SIP server',
        description: 'Accepts call event data from SIP server, validates it, logs to database, and queues for processing via RabbitMQ',
        operationId: 'storeCallEvent',
        tags: ['CallEvent'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Call event data from SIP server',
            content: new OA\JsonContent(
                ref: '#/components/schemas/StoreCallEventRequest'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event queued successfully',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/CallEventSuccessResponse'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationError'
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/InternalServerError'
                )
            ),
        ]
    )]
    public function store(CallEventRequest $request): JsonResponse
    {
        try {
            $dto = CallEventDTO::fromArray($request->validated());

            $this->service->handleCallEvent($dto);

            return response()->json(['status' => 'queued'], 200);
        } catch (\Throwable $e) {
            Log::error('Unexpected error processing call event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    #[OA\Get(
        path: '/api/v1/call-events/{call_id}',
        summary: 'Fetch the recorded event history for a call',
        description: 'Returns every call-event log row recorded for the given call_id, newest first.',
        operationId: 'getCallEventHistory',
        tags: ['CallEvent'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'call_id',
                description: 'The call_id to fetch history for',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event history for the call'
            ),
        ]
    )]
    public function history(string $callId): JsonResponse
    {
        $events = $this->service->getHistory($callId);

        return response()->json([
            'call_id' => $callId,
            'events' => $events->map(static fn (CallEventLog $log): array => [
                'id' => $log->id,
                'event_type' => $log->event_type->value,
                'payload' => $log->payload,
                'created_at' => $log->created_at,
            ])->all(),
        ]);
    }
}
