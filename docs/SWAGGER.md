# Swagger Documentation Standards

This directory contains the custom Swagger/OpenAPI documentation system based on PHP 8 Attributes.

## Overview

Instead of using native OpenAPI annotations (`@OA\*`), we use custom PHP Attributes for a cleaner, more maintainable approach to API documentation.

## Architecture

```
app/Support/Swagger/
├── Attributes/
│   ├── ApiEndpoint.php      # Endpoint metadata
│   ├── ApiParameter.php     # Parameter definitions
│   ├── ApiResource.php      # Resource metadata
│   └── ApiResponse.php      # Response definitions
├── Services/
│   └── SwaggerGenerator.php # Generates OpenAPI spec from attributes
└── README.md                # This file
```

## Usage

### 1. Define Resource Metadata (Class-Level)

Use `#[ApiResource]` attribute on controller classes:

```php
use App\Support\Swagger\Attributes\ApiResource;

#[ApiResource(
    name: 'CallEvent',
    description: 'Call event management from SIP server',
    model: CallEventLog::class,
    requestClass: CallEventRequest::class
)]
class CallEventController extends Controller
{
    // ...
}
```

**Parameters:**
- `name` (required) - Resource name (used in tags and schemas)
- `description` - Resource description
- `model` - Eloquent model class for auto-generating schemas
- `collection` - Custom collection class
- `requestClass` - Request class for auto-generating request body schema
- `updateRequestClass` - Request class for update operations

### 2. Define Endpoint Metadata (Method-Level)

Use `#[ApiEndpoint]` attribute on controller methods:

```php
use App\Support\Swagger\Attributes\ApiEndpoint;

#[ApiEndpoint(
    method: 'post',
    path: '/call-events',
    summary: 'Receive call event from SIP server',
    description: 'Accepts call event data from SIP server, validates it, logs to database, and queues for processing',
    responses: [200, 422, 500],
    authenticated: true
)]
public function store(CallEventRequest $request): JsonResponse
{
    // ...
}
```

**Parameters:**
- `method` (required) - HTTP method (get, post, put, patch, delete)
- `path` (required) - Endpoint path (relative to resource, e.g., `/`, `/{id}`)
- `summary` (required) - Short endpoint description
- `description` - Detailed endpoint description
- `parameters` - Array of parameter names
- `responses` - Array of response codes (e.g., [200, 422, 500])
- `authenticated` - Boolean, adds `bearerAuth` security (default: true)
- `permissions` - Array of required permissions

### 3. Define Custom Responses (Method-Level)

Use `#[ApiResponse]` attribute to customize response documentation:

```php
use App\Support\Swagger\Attributes\ApiResponse;

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
public function store(CallEventRequest $request): JsonResponse
{
    // ...
}
```

**Parameters:**
- `code` (required) - HTTP status code
- `description` (required) - Response description
- `schema` - Schema name from components/schemas
- `example` - Example response data (array)

### 4. Define Custom Parameters (Method-Level)

Use `#[ApiParameter]` attribute for custom query/path/header parameters:

```php
use App\Support\Swagger\Attributes\ApiParameter;

#[ApiParameter(
    name: 'filter',
    in: 'query',
    type: 'string',
    required: false,
    description: 'Filter results by field',
    example: 'status:active'
)]
public function index(Request $request): JsonResponse
{
    // ...
}
```

**Parameters:**
- `name` (required) - Parameter name
- `in` (required) - Parameter location (query, path, header, body)
- `type` - Parameter type (string, integer, boolean, etc.)
- `required` - Boolean, is parameter required
- `description` - Parameter description
- `example` - Example value
- `schema` - Custom schema (array)

## Auto-Generation Features

### From Model

When you specify `model` in `#[ApiResource]`, the generator automatically:
- Extracts fillable attributes
- Reads casts to determine property types
- Generates resource schema (single object)
- Generates collection schema (paginated response)

### From Request Class

When you specify `requestClass` or `updateRequestClass`, the generator:
- Reads validation rules
- Generates request body schema
- Determines required fields from `required` rule
- Infers property types from validation rules

### Standard CRUD Operations

For standard method names, the generator auto-creates endpoints:
- `index()` → GET /resource
- `store()` → POST /resource
- `show()` → GET /resource/{id}
- `update()` → PUT /resource/{id}
- `destroy()` → DELETE /resource/{id}

## Generating Documentation

Run the Artisan command to generate OpenAPI 3.0 spec:

```bash
php artisan swagger:generate-from-attributes
```

**Options:**
- `--controller=ClassName` - Process specific controller(s)
- `--path=app` - Base path for scanning controllers (default: app)

**Output:**
- File: `storage/api-docs/swagger.json`
- Format: OpenAPI 3.0 JSON

## Standard Schemas

The following schemas are auto-generated:

### ValidationError
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### UnauthorizedError
```json
{
  "error": "Unauthorized"
}
```

### InternalServerError
```json
{
  "error": "An unexpected error occurred."
}
```

## Example: Complete Controller

```php
<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Http\Controllers;

use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Http\Requests\CallEventRequest;
use App\Modules\CallEvent\Models\CallEventLog;
use App\Modules\CallEvent\Services\CallEventService;
use App\Support\Swagger\Attributes\ApiEndpoint;
use App\Support\Swagger\Attributes\ApiResource;
use App\Support\Swagger\Attributes\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

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
        description: 'Accepts call event data, validates, logs, and queues for processing',
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
        $dto = CallEventDTO::fromArray($request->validated());
        $this->service->handleCallEvent($dto);
        return response()->json(['status' => 'queued'], 200);
    }
}
```

## Benefits

1. **Type Safety** - PHP 8 attributes are validated at runtime
2. **IDE Support** - Full autocomplete and navigation
3. **Cleaner Code** - No docblock clutter
4. **Maintainable** - Changes to attributes are reflected in generated docs
5. **DRY** - Auto-generation from models and requests reduces duplication
6. **Consistent** - Enforces documentation standards across the codebase

## Best Practices

1. **Always use `#[ApiResource]` on controllers** - Provides context for all endpoints
2. **Specify models and request classes** - Enables auto-generation
3. **Use `#[ApiResponse]` for non-standard responses** - Provides clear examples
4. **Keep descriptions concise** - Summary is 1 line, description is 1-2 sentences
5. **Reference standard schemas** - Use `ValidationError`, `UnauthorizedError`, etc.
6. **Regenerate after changes** - Run `swagger:generate-from-attributes` after modifying attributes

## Migration from Native OpenAPI Annotations

**Before (Native @OA annotations):**
```php
/**
 * @OA\Post(
 *     path="/api/v1/call-events",
 *     summary="Receive call event",
 *     @OA\RequestBody(...),
 *     @OA\Response(...)
 * )
 */
public function store(Request $request) {}
```

**After (Custom Attributes):**
```php
#[ApiEndpoint(
    method: 'post',
    path: '/call-events',
    summary: 'Receive call event'
)]
#[ApiResponse(code: 200, description: 'Success')]
public function store(Request $request) {}
```

## Support

For issues or questions about the Swagger documentation system:
1. Check this README
2. Review `SwaggerGenerator.php` for implementation details
3. Examine `CallEventController.php` for a working example
