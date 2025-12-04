<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_event_logs', static function (Blueprint $table) {
            $table->id();
            $table->string('call_id', 100)->index();
            $table->string('event_type', 50);
            $table->json('payload');
            $table->unsignedInteger('created_at')->index()->comment('Unix timestamp');

            $table->index(['call_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_event_logs');
    }
};