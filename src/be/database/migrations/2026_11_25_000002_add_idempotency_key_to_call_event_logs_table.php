<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable (not NOT NULL): any row written before this migration has no
     * idempotency key and never will — backfilling one would require
     * re-deriving it from payload JSON for historical rows, which isn't worth
     * doing for a column whose only job is deduplicating *future* retries.
     * Unique still holds (Postgres allows multiple NULLs under a unique
     * index), so new rows are protected from the moment this ships.
     */
    public function up(): void
    {
        Schema::table('call_event_logs', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('call_event_logs', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });
    }
};
