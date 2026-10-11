# Call Event API - Call Events System

A REST API integrated with your SIP server - receives call data, stores it in the database and places it in a queue.

## 🚀 Getting Started

### Requirements
- Docker
- Make

### Installation

```bash
# 1. Layihəni klonlayın
git clone https://github.com/RasimAghayev/atl_tech.git
cd atl_tech

# 2. Hər şeyi avtomatik quraşdırın
make setup
```

This command prepares the following for you:
- Docker containers (PHP, Nginx, PostgreSQL, Redis, RabbitMQ)
- Database tables
- API token

### Usage

```bash
# API token-u əldə edin
make token

# Zəng hadisəsi göndərin
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKENINIZ" \
  -d '{
    "call_id": "CALL-001",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_started",
    "timestamp": "2025-12-04 10:30:00"
  }'
```

## 📖 Documentation

For detailed information about the project:

- **[Backend README](src/be/README.md)** - API usage, installation, main commands
- **[API Examples](src/be/app/Modules/CallEvent/API_EXAMPLES.md)** - Curl, PHP, Python examples
- **[Swagger Guide](docs/SWAGGER_GUIDE.md)** - API documentation and interactive testing
- **[Architecture](ARCHITECTURE.md)** - System design and DDD structure

## 🛠️ Main Commands

```bash
make setup      # İlk quraşdırma (hər şey avtomatik)
make up         # Sistemi başlat
make down       # Sistemi dayandır
make token      # API token göstər
make swagger    # API sənədini yarat
make logs       # Logları göstər
```

## 🌐 Endpoints

- **API:** http://localhost:8000
- **Swagger UI:** http://localhost:8000/api/documentation/list
- **PostgreSQL:** localhost:5432
- **RabbitMQ Management:** http://localhost:15672 (guest/guest)

## 📊 What Does It Do?

```
SIP Server → API → Validation → PostgreSQL + RabbitMQ
```

1. The SIP server sends call data
2. The API validates and acknowledges it
3. It stores the data in PostgreSQL
4. It queues the message in RabbitMQ
5. It can be processed by any consumer

## 🎯 Features

- REST API (Laravel 12)
- Bearer Token Authentication
- PostgreSQL Database
- RabbitMQ Message Queue
- Docker Compose
- Swagger/OpenAPI Documentation
- DDD Architecture
- Request Validation
- Unix Timestamp Optimization

## 🔧 Technologies

- **Backend:** Laravel 12 (PHP 8.4)
- **Database:** PostgreSQL 17
- **Queue:** RabbitMQ 3.13
- **Cache:** Redis 7
- **Web Server:** Nginx
- **Container:** Docker & Docker Compose

## 📝 Quick Example

```bash
# Quraşdır
make setup

# Token al
make token
# Output: ce_xxxxxxxxxxx

# Test et
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_xxxxxxxxxxx" \
  -d '{"call_id":"TEST-001","caller_number":"+994501234567","callee_number":"+994551234567","event_type":"call_started","timestamp":"2025-12-04 10:30:00"}'

# Cavab
{"status":"queued"}
```

## 🆘 Need Help?

**Token is not working:**
```bash
make token  # Get a new token
```

**System is not working:**
```bash
make down && make up
```

**Reset the database:**
```bash
make fresh
```

**More information:**
- [API_EXAMPLES.md](src/be/app/Modules/CallEvent/API_EXAMPLES.md) - Troubleshooting section
- Container logs: `make logs`
- Laravel logs: `src/be/storage/logs/laravel.log`

---

See the [src/be/README.md](src/be/README.md) file for more detailed information.
