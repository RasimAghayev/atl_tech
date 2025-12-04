# Call Event Module - Laravel + RabbitMQ

## Layihə haqqında

Bu modul SIP serverindən gələn zəng eventlərini qəbul edib RabbitMQ-ya göndərən Laravel servisidir. Layihə **Domain-Driven Design (DDD)** arxitekturasına uyğun qurulub və modern Laravel 12 best practice-lərindən istifadə edir.

## Arxitektura

### Struktur

```
app/
├── Modules/
│   └── CallEvent/                    # Call Event Domain Module
│       ├── Contracts/                # Interface definitions
│       │   └── CallEventRepositoryInterface.php
│       ├── DTOs/                     # Data Transfer Objects
│       │   └── CallEventDTO.php
│       ├── Enums/                    # Enumerations
│       │   └── CallEventTypeEnum.php
│       ├── Http/
│       │   ├── Controllers/          # HTTP Controllers
│       │   │   └── CallEventController.php
│       │   ├── Middleware/           # Custom middleware
│       │   │   └── VerifyApiToken.php
│       │   └── Requests/             # Form Requests
│       │       └── CallEventRequest.php
│       ├── Models/                   # Eloquent Models
│       │   └── CallEventLog.php
│       ├── Repositories/             # Repository implementations
│       │   └── PostgresCallEventRepository.php
│       ├── Routes/                   # Module routes
│       │   └── api.php
│       ├── Services/                 # Business logic
│       │   └── CallEventService.php
│       └── Providers/                # Service providers
│           └── CallEventServiceProvider.php
│
└── Shared/                           # Shared infrastructure
    ├── Brokers/
    │   └── RabbitMQ/
    │       └── RabbitMQPublisher.php # RabbitMQ integration
    ├── Config/
    │   └── call-event.php            # Module configuration
    ├── Contracts/
    │   └── EventPublisherInterface.php
    └── Exceptions/
        └── BrokerConnectionException.php
```

### Design Patterns

1. **Repository Pattern**: Data access layer abstraction
2. **DTO Pattern**: Type-safe data transfer
3. **Service Layer**: Business logic encapsulation
4. **Dependency Injection**: Loose coupling
5. **Interface Segregation**: Contract-based development

## Quraşdırma

### 1. Tələblər

- Docker & Docker Compose
- PHP 8.4+
- PostgreSQL 16+
- RabbitMQ 3.13+

### 2. Layihəni klonlayın

```bash
git clone https://github.com/RasimAghayev/atl_tech.git
cd atl_tech
```

### 3. Environment konfiqurasiyası

`.env` faylını yaradın və konfiqurasiya edin:

```bash
cp src/be/.env.example src/be/.env
```

Əsas parametrlər:

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=atltechcallcenterapi_db
DB_USERNAME=atltechcallcenterapi_user
DB_PASSWORD=secure_password

# Call Event API
CALL_EVENT_API_TOKEN=your-secret-api-token-change-this

# RabbitMQ
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
CALL_EVENT_QUEUE_NAME=call-events
```

### 4. Docker konteynerləri başladın

```bash
# Build and start all services
docker-compose up -d --build

# Or using Makefile
make setup

# or

make build
make up
```

### 5. Composer paketlərini yükləyin

```bash
docker-compose run --rm composer install

# Or using Makefile
make composer-install
```

### 6. Application key yaradın

```bash
docker-compose run --rm artisan key:generate
```

### 7. Database migration işlədin

```bash
docker-compose run --rm artisan migrate

# Or using Makefile
make migrate
```

## RabbitMQ İnteqrasiyası

### İş Prinsipi

1. **Connection**: `RabbitMQPublisher` class-ı RabbitMQ-ya qoşulur
2. **Queue Declaration**: `call-events` adlı durable queue yaradılır
3. **Message Publishing**: Validated event data JSON formatda queue-a göndərilir
4. **Error Handling**: Connection və publish xətaları log-lanır və exception atılır

### Konfiqurasiya

RabbitMQ parametrləri `config/call-event.php` faylında və `.env`-də təyin edilir:

```php
'rabbitmq' => [
    'host' => env('RABBITMQ_HOST', 'localhost'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
],
```

### Message Format

Queue-a göndərilən mesaj formatı:

```json
{
  "call_id": "CALL-12345",
  "caller_number": "+994501234567",
  "callee_number": "+994551234567",
  "event_type": "call_started",
  "timestamp": "2025-12-04T10:30:00+00:00",
  "duration": null
}
```

### RabbitMQ Management UI

Browser-də açın: `http://localhost:15672`

- Username: `guest`
- Password: `guest`

## API İstifadəsi

### Endpoint

```
POST /api/v1/call-events
```

### Authentication

Bearer Token authentication istifadə olunur. Token `.env` faylında `CALL_EVENT_API_TOKEN` parametri ilə təyin edilir.

```bash
Authorization: Bearer your-secret-api-token-change-this
```

### Request Payload

```json
{
  "call_id": "CALL-12345",
  "caller_number": "+994501234567",
  "callee_number": "+994551234567",
  "event_type": "call_started",
  "timestamp": "2025-12-04 10:30:00",
  "duration": null
}
```

### Event Types

- `call_started` - Zəng başladı
- `call_ended` - Zəng sona çatdı (duration required)
- `call_held` - Zəng gözləməyə alındı
- `call_transferred` - Zəng yönləndirildi
- `call_missed` - Buraxılmış zəng
- `call_answered` - Zəng cavablandırıldı

### Validation Rules

| Field          | Required | Rules                               | Notes                          |
| -------------- | -------- | ----------------------------------- | ------------------------------ |
| call_id        | Yes      | string, max:255                     |                                |
| caller_number  | Yes      | string, phone format (10-15 digits) |                                |
| callee_number  | Yes      | string, phone format (10-15 digits) |                                |
| event_type     | Yes      | enum (see Event Types)              |                                |
| timestamp      | Yes      | date (Y-m-d H:i:s)                  |                                |
| duration       | No       | integer, min:0                      | Required for `call_ended` only |

### Response Examples

**Success (200)**

```json
{
  "status": "queued"
}
```

**Validation Error (422)**

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "duration": ["Duration is required when event type is call_ended."]
  }
}
```

**Unauthorized (401)**

```json
{
  "error": "Unauthorized. Invalid or missing API token."
}
```

**Server Error (500)**

```json
{
  "error": "Failed to queue event. Please try again later."
}
```

### cURL Nümunəsi

```bash
curl -X POST http://localhost/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your-secret-api-token-change-this" \
  -d '{
    "call_id": "CALL-12345",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_started",
    "timestamp": "2025-12-04 10:30:00"
  }'
```

## Testing

### Unit və Feature testlər

```bash
# Run all tests
docker-compose run --rm artisan test

# Run specific test file
docker-compose run --rm artisan test tests/Feature/Modules/CallEvent/CallEventApiTest.php

# Run with coverage
docker-compose run --rm artisan test --coverage
```

### Test Coverage

`CallEventApiTest` aşağıdakı halları test edir:

- Successful event submission
- Call ended event with duration
- Duration validation for call_ended
- Invalid phone number validation
- Invalid event type validation
- Authentication without token
- Authentication with invalid token
- Required fields validation

## Database Schema

### `call_event_logs` cədvəli

| Column       | Type      | Description              |
| ------------ | --------- | ------------------------ |
| id           | bigint    | Primary key              |
| call_id      | string    | Call identifier (index)  |
| event_type   | string    | Event type enum          |
| payload      | json      | Full event data          |
| created_time | timestamp | Event creation timestamp |

### Indexes

- `call_id` - Single column index
- `(call_id, event_type)` - Composite index
- `created_time` - Single column index

## Logging

Bütün event və error-lar Laravel log sistemində qeyd olunur:

### Log Locations

- Application logs: `storage/logs/laravel.log`
- RabbitMQ connection logs
- Event processing logs
- Error traces

### Log Levels

- `INFO`: Successful operations
- `ERROR`: Failures and exceptions

## Təhlükəsizlik

### API Token Authentication

- Bearer token authentication
- Hash-based token comparison (timing attack safe)
- Environment-based configuration

### Validation

- Strict input validation
- SQL injection prevention (Eloquent ORM)
- XSS protection (JSON responses)

### Rate Limiting

Default: 60 requests per minute per IP

```php
Route::middleware(['throttle:60,1'])
```

## Troubleshooting

### RabbitMQ Connection Failed

```bash
# Check RabbitMQ status
docker-compose ps rabbitmq

# View RabbitMQ logs
docker-compose logs rabbitmq

# Restart RabbitMQ
docker-compose restart rabbitmq
```

### Database Connection Issues

```bash
# Check PostgreSQL status
docker-compose ps pgsql

# Access database
docker-compose exec pgsql psql -U atltechcallcenterapi_user -d atltechcallcenterapi_db
```

### View Application Logs

```bash
# Real-time logs
docker-compose logs -f php

# Or inside container
docker-compose exec php tail -f storage/logs/laravel.log
```

## Development

### Code Quality Tools

```bash
# PHP Pint (Code formatting)
docker-compose run --rm artisan pint

# PHPStan (Static analysis)
docker-compose run --rm artisan phpstan

# Rector (Code modernization)
docker-compose run --rm artisan rector:fix
```

### API Documentation

Swagger/OpenAPI documentation:

```
http://localhost/api/documentation
```

## Paketlər

### Production Dependencies

- `laravel/framework: ^12.41` - Laravel framework
- `php-amqplib/php-amqplib: ^3.7` - RabbitMQ PHP client
- `darkaonline/l5-swagger: ^9.0` - Swagger/OpenAPI integration

### Development Dependencies

- `phpunit/phpunit: ^12.0` - Testing framework
- `larastan/larastan: ^3.0` - PHPStan for Laravel
- `laravel/pint: ^1.26` - Code style fixer
- `rector/rector: ^2.0` - Automated refactoring

## Best Practices

1. **SOLID Principles**: Her class tək məsuliyyət daşıyır
2. **DRY (Don't Repeat Yourself)**: Kod təkrarlanmır
3. **KISS (Keep It Simple)**: Sadə və anlaşılan kod
4. **Type Safety**: Strict types və PHP 8.4 features
5. **Error Handling**: Comprehensive exception handling
6. **Logging**: Detailed logging for debugging
7. **Testing**: High test coverage

## Əlaqə

Suallarınız olarsa, issue açın və ya pull request göndərin.

## Lisenziya

MIT License