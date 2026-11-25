<?php

declare(strict_types=1);

namespace App\Shared\Outbox\Models;

use App\Shared\Outbox\OutboxStatus;
use Illuminate\Database\Eloquent\Model;

final class OutboxEvent extends Model
{
    protected $table = 'outbox_events';

    protected $fillable = [
        'idempotency_key',
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'queue_name',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'available_at',
        'published_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'status' => OutboxStatus::class,
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
