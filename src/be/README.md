# Call Event API

SIP serverdən gələn zəng hadisələrini qəbul edən və emal edən REST API.

## 🎯 Nə İşləyir?

Bu sistem SIP serverdən zəng məlumatlarını alır, yoxlayır, PostgreSQL-də saxlayır və RabbitMQ vasitəsilə növbəyə salır.

## ⚡ Sürətli Başlanğıc

### 1. Sistemi Quraşdırın

```bash
# Layihəni yüklə və quraşdır
make setup
```

Bu komanda hər şeyi avtomatik quraşdırır: Docker containerləri, baza, RabbitMQ, API token.

### 2. Token-u Əldə Edin

```bash
make token
```

Sizə token verəcək, məsələn: `ce_epJBbHUgBaCRzj5JtY7PyrDAKFEtX1KvZ3KIljLS`

### 3. API-ə Sorğu Göndərin

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SIZIN_TOKENINIZ" \
  -d '{
    "call_id": "CALL-001",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_started",
    "timestamp": "2025-12-04 10:30:00"
  }'
```

**Cavab:**
```json
{"status":"queued"}
```

✅ İşləyir! Məlumat həm bazada saxlanıldı, həm də RabbitMQ-ya göndərildi.

## 📝 Zəng Hadisə Növləri

Sistemə 6 növ zəng hadisəsi göndərə bilərsiniz:

- `call_started` - Zəng başladı
- `call_answered` - Zəng cavablandırıldı
- `call_ended` - Zəng bitdi ⚠️ `duration` (saniyə) göndərmək MƏCBUR
- `call_held` - Zəng gözləmədə
- `call_transferred` - Zəng yönləndirildi
- `call_missed` - Buraxılmış zəng

### Nümunə: Bitmış Zəng

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SIZIN_TOKENINIZ" \
  -d '{
    "call_id": "CALL-002",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_ended",
    "timestamp": "2025-12-04 10:35:00",
    "duration": 300
  }'
```

## 🛠️ Əsas Komandalar

```bash
make setup      # Hər şeyi quraşdır (ilk dəfə)
make up         # Sistemi başlat
make down       # Sistemi dayandır
make token      # API token göstər
make swagger    # API sənədləşdirməsi yarat
make logs       # Logları göstər
```

## 📚 Ətraflı Sənədlər

Daha çox məlumat üçün bu faylları oxuyun:

- **[API_EXAMPLES.md](app/Modules/CallEvent/API_EXAMPLES.md)** - Curl, PHP, Python nümunələri və troubleshooting
- **[SWAGGER_GUIDE.md](../../docs/SWAGGER_GUIDE.md)** - API sənədləşdirməsi və Swagger UI istifadəsi
- **[ARCHITECTURE.md](../../ARCHITECTURE.md)** - Sistem arxitekturası və DDD strukturu

## 🌐 Swagger UI

API-ni interaktiv test etmək üçün:

```
http://localhost:8000/api/documentation/list
```

1. Səhifəni açın
2. "Authorize" düyməsinə klikləyin
3. Token daxil edin (`make token` ilə əldə edin)
4. İstənilən endpoint-i test edin

## 🔧 Texnologiyalar

- **Laravel 12** - PHP Framework
- **PostgreSQL** - Məlumat bazası
- **RabbitMQ** - Mesaj növbəsi
- **Docker** - Konteynerləşdirmə
- **Swagger/OpenAPI** - API sənədləşdirməsi

## 📊 Sistem Strukturu

```
API Sorğu → Validation → Database Log → RabbitMQ Queue
                ↓              ↓              ↓
            422 Error    call_event_logs   Message
```

Hər zəng hadisəsi:
1. Yoxlanılır (validation)
2. Bazada saxlanılır (PostgreSQL)
3. Növbəyə salınır (RabbitMQ)

## ⚠️ Qeydlər

- **Token hər `make setup` zamanı dəyişir** - Yeni token üçün `make token` işlədin
- **`call_ended` üçün `duration` məcburidir** - Saniyə ilə göndərin
- **Telefon nömrələri beynəlxalq formatda** - `+994501234567` şəklində

## 🆘 Problem Olsa

Token işləmirsə:
```bash
make token  # Yeni token-u əldə et
```

Container işləmirsə:
```bash
make down
make up
```

Baza problemi varsa:
```bash
make fresh  # Bazanı sıfırla və yenidən qur
```

## 📞 Dəstək

Suallarınız olarsa:
1. Əvvəlcə [API_EXAMPLES.md](app/Modules/CallEvent/API_EXAMPLES.md) faylındakı Troubleshooting bölməsinə baxın
2. `storage/logs/laravel.log` faylında error log-larını yoxlayın
3. `make logs` ilə container log-larına baxın

---

**Qısa və aydın başlanğıc üçün:**
```bash
make setup          # Quraşdır
make token          # Token al
curl -X POST ...    # Test et
```

Hamısı bu qədər sadədir! 🚀
