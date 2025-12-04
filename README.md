# Call Event API - Zəng Hadisələri Sistemi

SIP serverinizlə inteqrasiya olunan REST API - zəng məlumatlarını qəbul edir, bazada saxlayır və növbəyə salır.

## 🚀 Başlamaq

### Tələblər
- Docker
- Make

### Quraşdırma

```bash
# 1. Layihəni klonlayın
git clone https://github.com/RasimAghayev/atl_tech.git
cd atl_tech

# 2. Hər şeyi avtomatik quraşdırın
make setup
```

Bu komanda sizin üçün hazırlayır:
- Docker containerləri (PHP, Nginx, PostgreSQL, Redis, RabbitMQ)
- Baza cədvəllərini
- API token-u

### İstifadə

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

## 📖 Sənədləşdirmə

Layihə haqqında ətraflı məlumat üçün:

- **[Backend README](src/be/README.md)** - API istifadəsi, quraşdırma, əsas komandalar
- **[API Nümunələri](src/be/app/Modules/CallEvent/API_EXAMPLES.md)** - Curl, PHP, Python nümunələri
- **[Swagger Guide](docs/SWAGGER_GUIDE.md)** - API sənədləşdirməsi və interaktiv test
- **[Arxitektura](ARCHITECTURE.md)** - Sistem dizaynı və DDD strukturu

## 🛠️ Əsas Komandalar

```bash
make setup      # İlk quraşdırma (hər şey avtomatik)
make up         # Sistemi başlat
make down       # Sistemi dayandır
make token      # API token göstər
make swagger    # API sənədini yarat
make logs       # Logları göstər
```

## 🌐 Əlaqələr

- **API:** http://localhost:8000
- **Swagger UI:** http://localhost:8000/api/documentation/list
- **PostgreSQL:** localhost:5432
- **RabbitMQ Management:** http://localhost:15672 (guest/guest)

## 📊 Nə Edir?

```
SIP Server → API → Validation → PostgreSQL + RabbitMQ
```

1. SIP server zəng məlumatı göndərir
2. API yoxlayır və təsdiqlə yir
3. PostgreSQL-də saxlayır
4. RabbitMQ-ya növbəyə salır
5. İstənilən consumer tərəfindən emal edilə bilər

## 🎯 Xüsusiyyətlər

- REST API (Laravel 12)
- Bearer Token Authentication
- PostgreSQL Database
- RabbitMQ Message Queue
- Docker Compose
- Swagger/OpenAPI Documentation
- DDD Architecture
- Request Validation
- Unix Timestamp Optimization

## 🔧 Texnologiyalar

- **Backend:** Laravel 12 (PHP 8.4)
- **Database:** PostgreSQL 17
- **Queue:** RabbitMQ 3.13
- **Cache:** Redis 7
- **Web Server:** Nginx
- **Container:** Docker & Docker Compose

## 📝 Qısa Nümunə

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

## 🆘 Kömək Lazımdır?

**Token işləmir:**
```bash
make token  # Yeni token əldə et
```

**Sistem işləmir:**
```bash
make down && make up
```

**Bazanı sıfırla:**
```bash
make fresh
```

**Daha çox məlumat:**
- [API_EXAMPLES.md](src/be/app/Modules/CallEvent/API_EXAMPLES.md) - Troubleshooting bölməsi
- Container logları: `make logs`
- Laravel logları: `src/be/storage/logs/laravel.log`

---

Daha ətraflı məlumat üçün [src/be/README.md](src/be/README.md) faylına baxın.
