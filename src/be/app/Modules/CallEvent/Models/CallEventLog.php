<?php

declare(strict_types=1);

namespace App\Modules\CallEvent\Models;

use App\Modules\CallEvent\Enums\CallEventTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallEventLog extends Model
{
    use HasFactory;

    protected $table = 'call_event_logs';

    public $timestamps = false;

    public const string CREATED_AT = 'created_at';

    protected $fillable = [
        'call_id',
        'event_type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'event_type' => CallEventTypeEnum::class,
        'payload' => 'array',
        'created_at' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $model) {
            if (! $model->created_at) {
                $model->created_at = time();
            }
        });
    }
}