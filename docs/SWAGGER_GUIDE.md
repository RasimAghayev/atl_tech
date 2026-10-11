# Swagger Documentation Guide

## 🎯 Overview

API documentation in this project is built on two standard packages:

1. **darkaonline/l5-swagger** (^9.0) - serves the interactive Swagger UI and the generated OpenAPI document
2. **zircote/swagger-php** - scans the code for `OpenApi\Attributes` (`#[OA\...]`) annotations and produces the OpenAPI 3.0 specification

There is no custom attribute system. Everything is declared with the standard `@OA\*` annotations from swagger-php, so any tooling that understands OpenAPI annotations will also understand this project.

> The old custom attributes (`ApiEndpoint`, `ApiResource`, `ApiResponse`, `ApiParameter`, `SwaggerGenerator`, and the `swagger:generate-from-attributes` command) have been removed. `l5-swagger:generate` is the only generation entry point now.

## 📦 How the Annotations Work

Annotations are PHP attributes. They carry no runtime behaviour - the generator reads them with reflection and writes the result to a JSON file.

Always import the attribute namespace with a short alias at the top of the file:

```php
use OpenApi\Attributes as OA;
```

### Class-level annotations

| Annotation | Purpose |
|---|---|
| `#[OA\Info]` | API title, version, description |
| `#[OA\Server]` | Base server URL |
| `#[OA\SecurityScheme]` | Declares a security scheme (for example `bearerAuth`) |
| `#[OA\Schema]` | Defines a reusable component schema |
| `#[OA\Tag]` | Groups operations under a tag |

Document-wide definitions live in a single class - in this project `app/Http/Controllers/SwaggerController.php`:

```php
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
class SwaggerController extends Controller
{
    // ...
}
```

Once `bearerAuth` is declared here, any operation can opt into it with `security: [['bearerAuth' => []]]`.

### Method-level annotations

Each documented endpoint method gets one HTTP verb annotation: `#[OA\Post]`, `#[OA\Get]`, `#[OA\Put]`, `#[OA\Patch]`, `#[OA\Delete]`. The method body stays ordinary PHP - the annotation is documentation only.

### Component schemas

Schemas are declared once with `#[OA\Schema(schema: 'Name', ...)]` and referenced from operations by JSON pointer:

```php
#[OA\Schema(
    schema: 'StoreCallEventRequest',
    required: ['call_id', 'caller_number', 'callee_number', 'event_type', 'timestamp'],
    properties: [
        new OA\Property(property: 'call_id', type: 'string', example: 'CALL-2025-001'),
        new OA\Property(property: 'caller_number', type: 'string', example: '+994501234567'),
        new OA\Property(property: 'callee_number', type: 'string', example: '+994551234567'),
        new OA\Property(property: 'event_type', type: 'string', example: 'call_started'),
        new OA\Property(property: 'timestamp', type: 'string', example: '2025-12-04 10:30:00'),
    ]
)]
```

Schemas currently declared in `SwaggerController.php`:

- `StoreCallEventRequest` - request body validation
- `CallEventSuccessResponse` - success payload
- `ValidationError` - standard 422 response
- `InternalServerError` - standard 500 response
- `UnauthorizedError` - standard 401 response
- `ForbiddenError` - standard 403 response

## 🔧 Documenting an Endpoint

Four building blocks cover almost every endpoint:

- **`#[OA\Parameter]`** - a query, path, or header parameter
- **`#[OA\RequestBody]`** - the request payload, with `#[OA\JsonContent]` for the media type and schema
- **`#[OA\Response]`** - one per status code, each with a description and optional content
- **`#[OA\JsonContent]`** - describes a JSON body or JSON response; `ref` points at a component schema

### Referencing a schema

`ref` uses the JSON pointer form, always prefixed with `#/components/schemas/`:

```php
ref: '#/components/schemas/StoreCallEventRequest'
```

Define the schema once in `SwaggerController.php`, then reference it. Never inline a large object in an operation when a named schema already exists.

### Inline example

```php
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'CallEvent', description: 'Call event management from SIP server')]
class CallEventController extends Controller
{
    #[OA\Post(
        path: '/api/v1/call-events',
        summary: 'Receive call event from SIP server',
        description: 'Accepts call event data, validates it, logs it, and queues it',
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
        parameters: [
            new OA\Parameter(
                name: 'X-Request-Id',
                in: 'header',
                description: 'Optional correlation id',
                required: false,
                schema: new OA\Schema(type: 'string')
            ),
        ],
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
        // ...
    }
}
```

## ✅ Worked Example: CallEventController

This is the real file at `app/Modules/CallEvent/Http/Controllers/CallEventController.php`, annotations included.

```php
<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Http\Controllers;

use App\Modules\CallEvent\DTOs\CallEventDTO;
use App\Modules\CallEvent\Http\Requests\CallEventRequest;
use App\Modules\CallEvent\Services\CallEventService;
use App\Shared\Exceptions\BrokerConnectionException;
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
```

Points worth noting in this example:

- The class carries only `#[OA\Tag]`; the schema definitions live in `SwaggerController.php`.
- `operationId` is set explicitly so generated client methods get a stable name.
- The 200 response references a schema, not an inline object, so the UI renders the same shape the API returns.
- Every error path the method can actually take (422, 500) has a documented response. Add an `#[OA\Response]` whenever a new branch is added to the method body.

## 📝 Generating the Documentation

### Command:

```bash
make swagger
```

This command:
1. Runs `l5-swagger:generate`, which scans the source for `@OA\*` annotations
2. Writes the resulting OpenAPI 3.0 document to `storage/api-docs/api-docs.json`
3. Prints the URL of the Swagger UI

### Manual generation:

```bash
docker-compose run --rm artisan l5-swagger:generate --no-interaction
```

To regenerate the specification for every configured documentation set:

```bash
docker compose run --rm artisan l5-swagger:generate --all
```

### Note on the scan paths

By default l5-swagger only scans `app/Http`. Controllers that live under a module path (`app/Modules/...`) must be added to `annotations` in `config/l5-swagger.php`, otherwise their annotations are silently skipped.

## 🌐 Viewing the Swagger UI

### Swagger UI (interactive):

```
http://localhost:8000/api/documentation/list
```

This page shows the full interactive Swagger UI, and you can call the API from it.

### Raw JSON specification:

```
http://localhost:8000/api-docs.json
```

The OpenAPI 3.0 specification in raw JSON format.

## 🧪 Testing in Swagger UI

1. **Open Swagger UI:**
   ```
   http://localhost:8000/api/documentation/list
   ```

2. **Click the Authorize button**

3. **Enter the API token:**
   ```bash
   # Get a token:
   make token
   ```

   Paste the token into the "Value" field in Swagger UI and click "Authorize".

4. **Test the endpoint:**
   - Expand POST `/api/v1/call-events`
   - Click "Try it out"
   - Fill in the request body
   - Click "Execute"

### Test request body:

```json
{
  "call_id": "CALL-2025-001",
  "caller_number": "+994501234567",
  "callee_number": "+994551234567",
  "event_type": "call_started",
  "timestamp": "2025-12-04 10:30:00"
}
```

Expected 200 response:

```json
{
  "status": "queued"
}
```

## 📊 Generated OpenAPI Structure

The generated `api-docs.json` contains the following sections.

### 1. API Information
```json
{
  "openapi": "3.0.0",
  "info": {
    "title": "ATL Tech Call Center API",
    "version": "1.0.0",
    "description": "API documentation for ATL Tech Call Center - Call Event Management"
  }
}
```

### 2. Servers
```json
{
  "servers": [
    {
      "url": "http://localhost:8000",
      "description": "Local development server"
    }
  ]
}
```

### 3. Security Schemes
```json
{
  "components": {
    "securitySchemes": {
      "bearerAuth": {
        "type": "http",
        "scheme": "bearer",
        "bearerFormat": "JWT",
        "description": "Enter JWT token in format: Bearer <token>"
      }
    }
  }
}
```

### 4. Schemas
```json
{
  "components": {
    "schemas": {
      "StoreCallEventRequest": {
        "type": "object",
        "required": ["call_id", "caller_number", "callee_number", "event_type", "timestamp"],
        "properties": {
          "call_id": {"type": "string", "example": "CALL-2025-001"}
        }
      }
    }
  }
}
```

### 5. Paths (endpoints)
```json
{
  "paths": {
    "/api/v1/call-events": {
      "post": {
        "summary": "Receive call event from SIP server",
        "security": [{"bearerAuth": []}],
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {"$ref": "#/components/schemas/StoreCallEventRequest"}
            }
          }
        },
        "responses": {
          "200": {
            "description": "Event queued successfully",
            "content": {
              "application/json": {
                "schema": {"$ref": "#/components/schemas/CallEventSuccessResponse"}
              }
            }
          },
          "422": {
            "description": "Validation error",
            "content": {
              "application/json": {
                "schema": {"$ref": "#/components/schemas/ValidationError"}
              }
            }
          },
          "500": {
            "description": "Internal server error",
            "content": {
              "application/json": {
                "schema": {"$ref": "#/components/schemas/InternalServerError"}
              }
            }
          }
        }
      }
    }
  }
}
```

## 📁 Files

```
src/be/
├── app/
│   ├── Http/Controllers/
│   │   └── SwaggerController.php  # @OA\Info, @OA\Server, @OA\SecurityScheme, @OA\Schema
│   └── Modules/CallEvent/
│       └── Http/Controllers/
│           └── CallEventController.php  # @OA\Tag, @OA\Post
├── storage/api-docs/
│   └── api-docs.json         # generated specification
└── config/
    └── l5-swagger.php        # l5-swagger configuration, scan paths, routes
```

## 🔄 Workflow

1. **Add annotations to the controller:**
   ```php
   #[OA\Tag(...)]
   #[OA\Post(...)]
   #[OA\Response(...)]
   ```

2. **Generate the documentation:**
   ```bash
   make swagger
   ```

3. **Check it in Swagger UI:**
   ```
   http://localhost:8000/api/documentation/list
   ```

4. **Test it:**
   - Enter the token
   - Send the request
   - Check the response

## 💡 Best Practices

1. **Put every endpoint under a `#[OA\Tag]`**
   - Keeps the Swagger UI grouped and navigable
   - The tag name should match the module name

2. **Annotate every public endpoint method**
   - Write a summary and a description; the description should say what the method does, not repeat the summary
   - Set an explicit `operationId`

3. **Reference named schemas instead of inline objects**
   - `ref: '#/components/schemas/Name'`
   - Keeps request and response shapes defined in one place

4. **Document every status code the method can return**
   - Including validation and internal error responses
   - Put the example on the schema, not on the operation

5. **Regenerate after any change**
   ```bash
   make swagger
   ```

## 🎨 Swagger UI Customization

To change the l5-swagger configuration:

```php
// config/l5-swagger.php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'ATL Tech Call Center API',
            ],
            'routes' => [
                'api' => 'api/documentation',  // UI URL
            ],
            'paths' => [
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
            ],
            'annotations' => [
                base_path('app/Http/Controllers'),
                base_path('app/Modules'),
            ],
        ],
    ],
];
```

The `annotations` list is the scan path list. A controller outside these directories contributes nothing to the generated document.

## 📚 Further Reading

- swagger-php attributes: https://zircote.github.io/swagger-php/reference/attributes.html
- l5-swagger: https://l5-swagger.l5-swagger.io/
- OpenAPI 3.0 specification: https://swagger.io/specification/
- Call Event module: `docs/CALL_EVENT_MODULE_README.md`

## 🐛 Troubleshooting

### Swagger UI does not open

```bash
# Check the routes
docker-compose exec php php artisan route:list | grep docs

# Clear the cache
docker-compose exec php php artisan route:clear
```

### The JSON is not generated

```bash
# Generate manually
docker-compose run --rm artisan l5-swagger:generate --no-interaction

# Inspect the result
docker-compose exec php cat /var/www/html/be/storage/api-docs/api-docs.json
```

### Annotations are not picked up

```bash
# Clear the composer autoloader cache
docker-compose run --rm composer dump-autoload

# Clear the config cache
docker-compose exec php php artisan config:clear
```

### An endpoint is missing from the UI

The controller is most likely outside the configured scan paths. Add its directory to `annotations` in `config/l5-swagger.php`, clear the config cache, and regenerate.

## ✅ Summary

- **Stack:** `darkaonline/l5-swagger` + `zircote/swagger-php` `@OA\*` attributes
- **Definitions:** `app/Http/Controllers/SwaggerController.php`
- **Operations:** `#[OA\Post]`, `#[OA\Get]`, and so on, on the controller method
- **Schema references:** `#/components/schemas/Name`
- **Generate:** `make swagger`
- **View UI:** `http://localhost:8000/api/documentation/list`
- **JSON Spec:** `storage/api-docs/api-docs.json`
