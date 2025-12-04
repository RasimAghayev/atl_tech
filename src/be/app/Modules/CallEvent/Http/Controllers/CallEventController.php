<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Http\Controllers;

use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Http\Requests\CallEventRequest;
use App\Modules\CallEvent\Models\CallEventLog;
use App\Modules\CallEvent\Services\CallEventService;
use App\Shared\Exceptions\BrokerConnectionException;
use App\Support\Swagger\Attributes\ApiEndpoint;
use App\Support\Swagger\Attributes\ApiResource;
use App\Support\Swagger\Attributes\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

#[ApiResource(
    name: 'CallEvent',
    description: 'Call event management from SIP server',
    model: CallEventLog::class,
    requestClass: CallEventRequest::class
)]
class CallEventController extends Controller
{
    public function __construct(
        private readonly CallEventService $service
    ) {
    }

    #[ApiEndpoint(
        method: 'post',
        path: '/call-events',
        summary: 'Receive call event from SIP server',
        description: 'Accepts call event data from SIP server, validates it, logs to database, and queues for processing via RabbitMQ',
        responses: [200, 422, 500],
        authenticated: true
    )]
    #[ApiResponse(
        code: 200,
        description: 'Event queued successfully',
        example: ['status' => 'queued']
    )]
    #[ApiResponse(
        code: 422,
        description: 'Validation error',
        schema: 'ValidationError'
    )]
    #[ApiResponse(
        code: 500,
        description: 'Internal server error',
        schema: 'InternalServerError'
    )]
    public function store(CallEventRequest $request): JsonResponse
    {
        try {
            $dto = CallEventDTO::fromArray($request->validated());

            $this->service->handleCallEvent($dto);

            return response()->json(['status' => 'queued'], 200);
        } catch (BrokerConnectionException $e) {
            Log::error('Failed to queue call event', [
                'error' => $e->getMessage(),
                'request' => $request->validated(),
            ]);

            return response()->json([
                'error' => 'Failed to queue event. Please try again later.',
            ], 500);
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
}