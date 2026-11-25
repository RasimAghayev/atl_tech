<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shared\Outbox\OutboxRelayService;
use Illuminate\Console\Command;

/**
 * Drains outbox_events to the message broker (ADR-0001). Intended to run as
 * a long-lived worker process (its own container/supervisor-managed process,
 * scaled to N instances — claiming is safe to run concurrently, see
 * OutboxRepository::claimBatch()). `--once` processes a single batch and
 * exits, for a cron-driven deployment instead of a long-lived worker.
 */
final class RelayOutboxEvents extends Command
{
    protected $signature = 'outbox:relay {--once : Process a single batch and exit instead of looping forever}';

    protected $description = 'Relay pending outbox_events rows to the message broker';

    public function __construct(private readonly OutboxRelayService $relay)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $sleepSeconds = (int) config('outbox.relay_sleep_seconds');

        do {
            $published = $this->relay->relayBatch();

            if ($published > 0) {
                $this->info("Relayed {$published} outbox event(s).");
            }

            if ($this->option('once')) {
                break;
            }

            if ($published === 0) {
                sleep($sleepSeconds);
            }
        } while (true);

        return self::SUCCESS;
    }
}
