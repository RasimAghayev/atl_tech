<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transactional outbox (ADR-0001): a durable, atomic record of "this aggregate
     * produced this event", written in the SAME database transaction as the
     * business row it describes. A separate relay worker (outbox:relay) is the
     * only thing that ever talks to the message broker, publishing rows from here
     * and marking them published/failed — so a broker outage can never desync the
     * database from "what we told downstream systems", and the relay can be run as
     * N horizontally-scaled instances because claiming a batch uses
     * `FOR UPDATE SKIP LOCKED` (see App\Shared\Outbox\OutboxRepository).
     */
    public function up(): void
    {
        Schema::create('outbox_events', static function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 64)->unique();
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 100);
            $table->string('event_type', 50);
            $table->string('queue_name', 100);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts');
            $table->timestamp('available_at');
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // The relay's claim query filters on exactly these two columns.
            $table->index(['status', 'available_at'], 'outbox_events_status_available_at_idx');
            // Lets an operator ask "what has this call_id produced" without a table scan.
            $table->index(['aggregate_type', 'aggregate_id'], 'outbox_events_aggregate_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
