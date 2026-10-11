<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'ATL Tech Call Center API',
    version: '1.0.0',
    description: 'API documentation for ATL Tech Call Center - Call Event Management'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Enter JWT token in format: Bearer <token>'
)]
#[OA\Schema(
    schema: 'StoreCallEventRequest',
    required: ['call_id', 'caller_number', 'callee_number', 'event_type', 'timestamp'],
    properties: [
        new OA\Property(
            property: 'call_id',
            type: 'string',
            maxLength: 255,
            description: 'Unique call identifier'
        ),
        new OA\Property(
            property: 'caller_number',
            type: 'string',
            pattern: '^\+?[0-9]{10,15}$',
            description: 'Caller phone number in E.164 format'
        ),
        new OA\Property(
            property: 'callee_number',
            type: 'string',
            pattern: '^\+?[0-9]{10,15}$',
            description: 'Callee phone number in E.164 format'
        ),
        new OA\Property(
            property: 'event_type',
            type: 'string',
            enum: ['call_started', 'call_ended', 'call_held', 'call_transferred', 'call_missed', 'call_answered'],
            description: 'Type of call event'
        ),
        new OA\Property(
            property: 'timestamp',
            type: 'string',
            format: 'date-time',
            description: 'Event timestamp in Y-m-d H:i:s format'
        ),
        new OA\Property(
            property: 'duration',
            type: 'integer',
            minimum: 0,
            nullable: true,
            description: 'Call duration in seconds (required for call_ended event)'
        ),
    ]
)]
#[OA\Schema(
    schema: 'CallEventSuccessResponse',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'status',
            type: 'string',
            example: 'queued',
            description: 'Processing status'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'The given data was invalid.'
        ),
        new OA\Property(
            property: 'errors',
            type: 'object',
            additionalProperties: new OA\Items(type: 'array', items: new OA\Items(type: 'string')),
            description: 'Validation error messages by field'
        ),
    ]
)]
#[OA\Schema(
    schema: 'InternalServerError',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'error',
            type: 'string',
            example: 'An unexpected error occurred.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'UnauthorizedError',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Unauthenticated.'
        ),
    ]
)]
#[OA\Schema(
    schema: 'ForbiddenError',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'message',
            type: 'string',
            example: 'Forbidden.'
        ),
    ]
)]
class SwaggerController {}
