# Swagger Documentation Guide

## 🎯 Overview

Bu layihədə 2 növ Swagger documentation mövcuddur:

1. **Custom PHP Attributes** - Bizim yaratdığımız sistem
2. **L5-Swagger (OpenAPI UI)** - Swagger UI ilə interaktiv documentation

## 📝 Swagger Generate Etmək

### Komanda:

```bash
make swagger
```

Bu komanda:
1. PHP Attributes-dən OpenAPI 3.0 spec generate edir
2. `storage/api-docs/swagger.json` faylına yazır
3. L5-Swagger üçün `api-docs.json`-a kopyalayır

### Manual Generate:

```bash
docker-compose run --rm artisan swagger:generate-from-attributes --path="app"
```

## 🌐 Swagger UI-a Baxmaq

### Swagger UI (Interactive):

```
http://localhost:8000/api/documentation/list
```

Bu səhifə tam interaktiv Swagger UI göstərir və API-ni test edə bilərsiniz.

### OpenAPI JSON Spec:

```
http://localhost:8000/docs
```

Raw JSON formatında OpenAPI 3.0 spesifikasiyası.

## 🔧 Custom Attributes İstifadəsi

### Controller-də:

```php
use App\Support\Swagger\Attributes\ApiResource;
use App\Support\Swagger\Attributes\ApiEndpoint;
use App\Support\Swagger\Attributes\ApiResponse;

#[ApiResource(
    name: 'CallEvent',
    description: 'Call event management from SIP server',
    model: CallEventLog::class,
    requestClass: CallEventRequest::class
)]
class CallEventController extends Controller
{
    #[ApiEndpoint(
        method: 'post',
        path: '/call-events',
        summary: 'Receive call event from SIP server',
        description: 'Accepts call event data, validates, logs, and queues',
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
    public function store(CallEventRequest $request): JsonResponse
    {
        // ...
    }
}
```

## 📊 Generated OpenAPI Structure

Generate olunan `swagger.json` faylı aşağıdakıları ehtiva edir:

### 1. API Information
```json
{
  "openapi": "3.0.0",
  "info": {
    "title": "Call Event API",
    "version": "1.0.0",
    "description": "API for receiving and processing call events from SIP server"
  }
}
```

### 2. Servers
```json
{
  "servers": [
    {
      "url": "http://localhost:8000/api",
      "description": "API Server"
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
        "bearerFormat": "token",
        "description": "Enter your API token"
      }
    }
  }
}
```

### 4. Schemas (Auto-generated)

Modellərdən avtomatik generate olunur:
- `CallEventResource` - Tək obyekt
- `CallEventCollection` - Collection (pagination ilə)
- `StoreCallEventRequest` - Request body validation
- `ValidationError` - Standart error response

### 5. Paths (Endpoints)

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
          "200": {...},
          "422": {...},
          "500": {...}
        }
      }
    }
  }
}
```

## 🧪 Swagger UI-da Test Etmək

1. **Swagger UI-ı açın:**
   ```
   http://localhost:8000/api/documentation/list
   ```

2. **Authorize düyməsinə klikləyin**

3. **API Token daxil edin:**
   ```bash
   # Token-u əldə edin:
   make token
   ```

   Token-u Swagger UI-da "Value" sahəsinə yapışdırın və "Authorize" klikləyin.

4. **Endpoint-i test edin:**
   - POST `/api/v1/call-events` açın
   - "Try it out" klikləyin
   - Request body-ni doldurun
   - "Execute" klikləyin

### Test Request Body:

```json
{
  "call_id": "CALL-2025-001",
  "caller_number": "+994501234567",
  "callee_number": "+994551234567",
  "event_type": "call_started",
  "timestamp": "2025-12-04 10:30:00"
}
```

## 📁 Fayllar

```
src/be/
├── app/
│   ├── Support/Swagger/
│   │   ├── Attributes/          # Custom attributes
│   │   │   ├── ApiEndpoint.php
│   │   │   ├── ApiParameter.php
│   │   │   ├── ApiResource.php
│   │   │   └── ApiResponse.php
│   │   ├── Services/
│   │   │   └── SwaggerGenerator.php
│   │   └── README.md
│   └── Modules/CallEvent/
│       └── Http/Controllers/
│           └── CallEventController.php  # Attributes ilə
├── storage/api-docs/
│   ├── swagger.json          # Bizim generate etdiyimiz
│   └── api-docs.json         # L5-Swagger üçün copy
└── config/
    └── l5-swagger.php        # L5-Swagger konfiqurasiyası
```

## 🔄 Workflow

1. **Controller-ə Attributes əlavə et:**
   ```php
   #[ApiResource(...)]
   #[ApiEndpoint(...)]
   #[ApiResponse(...)]
   ```

2. **Swagger Generate et:**
   ```bash
   make swagger
   ```

3. **Swagger UI-da yoxla:**
   ```
   http://localhost:8000/api/documentation/list
   ```

4. **Test et:**
   - Token daxil et
   - Request göndər
   - Response yoxla

## 💡 Best Practices

1. **Hər controller-də `#[ApiResource]` istifadə et**
   - Resource metadata təmin edir
   - Auto-generation üçün lazımdır

2. **`#[ApiEndpoint]` hər public method üçün**
   - Summary və description yaz
   - Responses array-i təyin et

3. **Custom responses üçün `#[ApiResponse]` istifadə et**
   - Example data göstər
   - Schema reference et

4. **Model və Request class-ları specify et**
   - Auto-generation işə düşər
   - Schemas avtomatik generate olar

5. **Dəyişiklikdən sonra regenerate et**
   ```bash
   make swagger
   ```

## 🎨 Swagger UI Customization

L5-Swagger konfiqurasiyasını dəyişmək üçün:

```php
// config/l5-swagger.php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Call Event API',
            ],
            'routes' => [
                'api' => 'docs',  // /docs URL-i
            ],
            'paths' => [
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',
            ],
        ],
    ],
];
```

## 📚 Əlavə Məlumat

- Custom Attributes Guide: `src/be/app/Support/Swagger/README.md`
- API Examples: `src/be/app/Modules/CallEvent/API_EXAMPLES.md`
- OpenAPI 3.0 Spec: https://swagger.io/specification/

## 🐛 Troubleshooting

### Swagger UI açılmır

```bash
# Route-ları yoxla
docker-compose exec php php artisan route:list | grep docs

# Cache təmizlə
docker-compose exec php php artisan route:clear
```

### JSON generate olmur

```bash
# Manual generate et
docker-compose run --rm artisan swagger:generate-from-attributes --path="app"

# Yoxla
docker-compose exec php cat /var/www/html/be/storage/api-docs/swagger.json
```

### Attributes tanınmır

```bash
# Composer cache təmizlə
docker-compose run --rm composer dump-autoload

# Config cache təmizlə
docker-compose exec php php artisan config:clear
```

## ✅ Summary

- **Generate:** `make swagger`
- **View UI:** `http://localhost:8000/docs`
- **JSON Spec:** `storage/api-docs/swagger.json`
- **Attributes:** Custom PHP 8 Attributes
- **Auto-generation:** Model və Request class-lardan
