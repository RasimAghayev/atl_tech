# Call Event API - Call Events System
![Views](https://hits.sh/github.com/RasimAghayev/atl_tech.svg?label=views&color=0e75b6)


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
- Docker containers (PHP, Nginx, PostgreSQL, Redis, RabbitMQ, outbox relay worker)
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
- **Health (liveness):** http://localhost:8000/health/live
- **Health (readiness):** http://localhost:8000/health/ready
- **PostgreSQL:** localhost:6432 (see `DB_PORT` in `.env`)
- **RabbitMQ Management:** http://localhost:15672 (guest/guest)

## 📊 What Does It Do?

```
SIP Server → API → PostgreSQL + outbox_events (one transaction)
                                       |
                          outbox:relay worker (N instances)
                                       |
                                   RabbitMQ
```

1. The SIP server sends call data to the API.
2. The API validates it and, in a single DB transaction, writes the call-event
   log row **and** an outbox row — see
   [ADR-0001](docs/adr/0001-transactional-outbox.md). The request never talks
   to RabbitMQ directly, so a broker outage never turns into a 500 here.
3. An independent `outbox:relay` worker (its own container, horizontally
   scalable) drains the outbox and publishes to RabbitMQ, with retry/backoff
   and dead-lettering on repeated failure.
4. The same event is deduplicated end-to-end via a server-computed
   idempotency key — a retried submission is a safe no-op.
5. It can be processed by any RabbitMQ consumer.

## 🎯 Features

- REST API (Laravel 13)
- Bearer Token Authentication
- Transactional outbox for reliable, idempotent event delivery (ADR-0001)
- Call-event history read endpoint (`GET /api/v1/call-events/{call_id}`)
- Liveness/readiness health endpoints for orchestrators/load balancers
- PostgreSQL Database
- RabbitMQ Message Queue
- Docker Compose (multi-stage build, build-time test gate)
- Swagger/OpenAPI Documentation
- DDD Architecture
- Request Validation

## 🔧 Technologies

- **Backend:** Laravel 13 (PHP 8.5)
- **Database:** PostgreSQL 19
- **Queue:** RabbitMQ 4.4
- **Cache:** Redis 8
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
