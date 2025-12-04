# Call Event API - İstifadə Nümunələri

Bu sənəd Call Event API-ə necə sorğu göndərməyi izah edir.

## 🔐 Authentication

API Bearer Token autentifikasiyasından istifadə edir. Token `.env` faylında `CALL_EVENT_API_TOKEN` kimi təyin olunur.

### Token əldə etmək

**Əsas Yol (Makefile ilə):**
```bash
make token
```

Bu komanda aktiv token-u göstərəcək.

**Alternativ Yol (Docker ilə):**
```bash
docker-compose exec php grep CALL_EVENT_API_TOKEN /var/www/html/be/.env
```

**Yeni Token Generate Etmək:**
```bash
make seed
# və ya
docker-compose exec php php artisan db:seed --class=CallEventSeeder
```

⚠️ **ƏHƏM QEYD:**
- `make setup` və ya `make seed` hər dəfə **YENİ** token generate edir
- Köhnə token işləməyəcək, yeni token-u `make token` ilə əldə edin
- Token `.env` faylında `CALL_EVENT_API_TOKEN` kimi saxlanır

**Token Formatı:**
```
ce_<40_random_characters>
```

**Nümunə:**
```
ce_epJBbHUgBaCRzj5JtY7PyrDAKFEtX1KvZ3KIljLS
```

## 📍 API Endpoint

```
POST /api/v1/call-events
```

**Base URL:** `http://localhost:8000` (və ya sizin APP_URL)

## 📋 Request Format

### Headers

```http
Content-Type: application/json
Authorization: Bearer {YOUR_API_TOKEN}
```

### Request Body Structure

```json
{
  "call_id": "string",
  "caller_number": "string",
  "callee_number": "string",
  "event_type": "string",
  "timestamp": "YYYY-MM-DD HH:mm:ss",
  "duration": integer (optional)
}
```

### Field Validation Rules

| Field | Type | Required | Format | Description |
|-------|------|----------|--------|-------------|
| `call_id` | string | ✅ Yes | Max 255 chars | Unikal zəng identifikatoru |
| `caller_number` | string | ✅ Yes | +994501234567 | Zəng edənin nömrəsi (10-15 rəqəm) |
| `callee_number` | string | ✅ Yes | +994551234567 | Qəbul edənin nömrəsi (10-15 rəqəm) |
| `event_type` | string | ✅ Yes | Enum | Zəng hadisəsinin tipi |
| `timestamp` | string | ✅ Yes | Y-m-d H:i:s | Hadisənin baş vermə vaxtı |
| `duration` | integer | ⚠️ Conditional | Min: 0 | Zəng müddəti (saniyə, `call_ended` üçün vacib) |

### Event Types (Enum)

```
call_started     - Zəng başladı
call_ended       - Zəng bitdi (duration tələb olunur)
call_held        - Zəng gözləmədə
call_transferred - Zəng yönləndirildi
call_missed      - Buraxılmış zəng
call_answered    - Zəng cavablandırıldı
```

## 📝 Nümunələr

### 1. Call Started (Zəng Başladı)

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "CALL-2025-001",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_started",
    "timestamp": "2025-12-04 10:30:00"
  }'
```

**Response (200 OK):**
```json
{
  "status": "queued"
}
```

### 2. Call Ended (Zəng Bitdi)

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "CALL-2025-001",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_ended",
    "timestamp": "2025-12-04 10:32:30",
    "duration": 150
  }'
```

**Response (200 OK):**
```json
{
  "status": "queued"
}
```

### 3. Call Answered (Zəng Cavablandırıldı)

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "CALL-2025-002",
    "caller_number": "+994701234567",
    "callee_number": "+994771234567",
    "event_type": "call_answered",
    "timestamp": "2025-12-04 11:15:30"
  }'
```

### 4. Call Missed (Buraxılmış Zəng)

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "CALL-2025-003",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_missed",
    "timestamp": "2025-12-04 12:00:00"
  }'
```

### 5. Call Transferred (Zəng Yönləndirildi)

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "CALL-2025-004",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_transferred",
    "timestamp": "2025-12-04 13:20:00"
  }'
```

### 6. Call Held (Zəng Gözləmədə)

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "CALL-2025-005",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_held",
    "timestamp": "2025-12-04 14:45:00"
  }'
```

## ❌ Error Responses

### 1. Validation Error (422)

**Səbəb:** Düzgün olmayan məlumat

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "",
    "caller_number": "invalid",
    "event_type": "unknown_type"
  }'
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "call_id": [
      "Call ID is required."
    ],
    "caller_number": [
      "Caller number must be a valid phone number."
    ],
    "callee_number": [
      "Callee number is required."
    ],
    "event_type": [
      "Invalid event type. Allowed values: call_started, call_ended, call_held, call_transferred, call_missed, call_answered"
    ],
    "timestamp": [
      "Timestamp is required."
    ]
  }
}
```

### 2. Unauthorized Error (401)

**Səbəb:** Token yoxdur və ya düzgün deyil

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -d '{
    "call_id": "CALL-123",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_started",
    "timestamp": "2025-12-04 10:30:00"
  }'
```

**Response (401 Unauthorized):**
```json
{
  "error": "Unauthorized"
}
```

### 3. Missing Duration for call_ended (422)

**Səbəb:** `call_ended` event üçün `duration` göndərilməyib

```bash
curl -X POST http://localhost:8000/api/v1/call-events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8" \
  -d '{
    "call_id": "CALL-2025-001",
    "caller_number": "+994501234567",
    "callee_number": "+994551234567",
    "event_type": "call_ended",
    "timestamp": "2025-12-04 10:32:30"
  }'
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "duration": [
      "Duration is required when event type is call_ended."
    ]
  }
}
```

### 4. Internal Server Error (500)

**Səbəb:** RabbitMQ bağlantısı olmadıqda və ya gözlənilməz xəta

**Response (500 Internal Server Error):**
```json
{
  "error": "Failed to queue event. Please try again later."
}
```

## 🧪 Postman Collection

### Import edilməsi üçün JSON

```json
{
  "info": {
    "name": "Call Event API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000",
      "type": "string"
    },
    {
      "key": "api_token",
      "value": "ce_your_token_here",
      "type": "string"
    }
  ],
  "item": [
    {
      "name": "Call Started",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "Authorization",
            "value": "Bearer {{api_token}}"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"call_id\": \"CALL-2025-001\",\n  \"caller_number\": \"+994501234567\",\n  \"callee_number\": \"+994551234567\",\n  \"event_type\": \"call_started\",\n  \"timestamp\": \"2025-12-04 10:30:00\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/call-events",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "call-events"]
        }
      }
    },
    {
      "name": "Call Ended",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "Authorization",
            "value": "Bearer {{api_token}}"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"call_id\": \"CALL-2025-001\",\n  \"caller_number\": \"+994501234567\",\n  \"callee_number\": \"+994551234567\",\n  \"event_type\": \"call_ended\",\n  \"timestamp\": \"2025-12-04 10:32:30\",\n  \"duration\": 150\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/call-events",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "call-events"]
        }
      }
    },
    {
      "name": "Call Answered",
      "request": {
        "method": "POST",
        "header": [
          {
            "key": "Content-Type",
            "value": "application/json"
          },
          {
            "key": "Authorization",
            "value": "Bearer {{api_token}}"
          }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"call_id\": \"CALL-2025-002\",\n  \"caller_number\": \"+994701234567\",\n  \"callee_number\": \"+994771234567\",\n  \"event_type\": \"call_answered\",\n  \"timestamp\": \"2025-12-04 11:15:30\"\n}"
        },
        "url": {
          "raw": "{{base_url}}/api/v1/call-events",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "call-events"]
        }
      }
    }
  ]
}
```

Bu collection-u Postman-a import etmək üçün:
1. Postman-ı açın
2. Import → Raw text
3. Yuxarıdakı JSON-u yapışdırın
4. Variables-də `api_token` dəyərini dəyişdirin

## 🔧 PHP Nümunəsi

```php
<?php

$apiToken = 'ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8';
$baseUrl = 'http://localhost:8000';

$data = [
    'call_id' => 'CALL-2025-001',
    'caller_number' => '+994501234567',
    'callee_number' => '+994551234567',
    'event_type' => 'call_started',
    'timestamp' => date('Y-m-d H:i:s'),
];

$ch = curl_init($baseUrl . '/api/v1/call-events');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiToken,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "Success: " . $response;
} else {
    echo "Error: " . $response;
}
```

## 🐍 Python Nümunəsi

```python
import requests
from datetime import datetime

API_TOKEN = 'ce_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8'
BASE_URL = 'http://localhost:8000'

headers = {
    'Content-Type': 'application/json',
    'Authorization': f'Bearer {API_TOKEN}'
}

data = {
    'call_id': 'CALL-2025-001',
    'caller_number': '+994501234567',
    'callee_number': '+994551234567',
    'event_type': 'call_started',
    'timestamp': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
}

response = requests.post(
    f'{BASE_URL}/api/v1/call-events',
    json=data,
    headers=headers
)

if response.status_code == 200:
    print(f"Success: {response.json()}")
else:
    print(f"Error: {response.status_code} - {response.json()}")
```

## 📊 Database-də Nəticə

Uğurlu sorğu göndərdikdə `call_event_logs` cədvəlində aşağıdakı məlumat saxlanılır:

```sql
SELECT * FROM call_event_logs WHERE call_id = 'CALL-2025-001';
```

| id | call_id | event_type | payload | created_at |
|----|---------|------------|---------|------------|
| 1 | CALL-2025-001 | call_started | {"call_id":"CALL-2025-001","caller_number":"+994501234567","callee_number":"+994551234567","event_type":"call_started","timestamp":"2025-12-04 10:30:00"} | 1733306400 |

**Qeyd:** `created_at` unix timestamp formatındadır (integer).

## 🐰 RabbitMQ-da Nəticə

Event həmçinin RabbitMQ queue-ya göndərilir:

**Queue:** `call-events` (default, `.env`-də dəyişdirilə bilər)

**Message Format:**
```json
{
  "call_id": "CALL-2025-001",
  "caller_number": "+994501234567",
  "callee_number": "+994551234567",
  "event_type": "call_started",
  "timestamp": "2025-12-04 10:30:00",
  "duration": null
}
```

RabbitMQ Management UI-da yoxlamaq üçün:
```
http://localhost:15672
Username: guest
Password: guest
```

## 💡 Best Practices

1. **Call ID formatı:** Unikal olsun (məs: `CALL-YYYY-NNNN` və ya UUID)
2. **Phone number formatı:** Beynəlxalq format istifadə edin (`+994...`)
3. **Timestamp:** Server timezone-dan asılı olmadan dəqiq vaxt göndərin
4. **Duration:** Saniyə ilə göndərin, `call_ended` üçün mütləq
5. **Token security:** Token-u heç vaxt public repository-də saxlamayın
6. **Error handling:** 422/500 cavablarını düzgün handle edin
7. **Retry logic:** Network xətası olarsa retry mexanizmi quraşdırın

## 🔍 Troubleshooting

### Token işləmir
```bash
# Token-u yoxlayın
grep CALL_EVENT_API_TOKEN .env

# Yeni token generate edin
php artisan db:seed --class=CallEventSeeder
```

### RabbitMQ bağlantı xətası
```bash
# RabbitMQ işləyir?
docker ps | grep rabbitmq

# RabbitMQ-nu yenidən başladın
docker-compose restart rabbitmq
```

### Validation xətası
- Request body-ni JSON format validator ilə yoxlayın
- Phone number regex-ə uyğundur? (`+` ilə başlayır, 10-15 rəqəm)
- Timestamp formatı düzdür? (`Y-m-d H:i:s`)
- Event type düzdür? (6 variant var)

## 📞 Dəstək

Problemlərlə qarşılaşdıqda:
1. Log fayllarını yoxlayın: `storage/logs/laravel.log`
2. RabbitMQ management UI-ı yoxlayın
3. Database-də event saxlanıb-saxlanmadığını yoxlayın
