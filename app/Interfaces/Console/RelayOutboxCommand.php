<?php

declare(strict_types=1);

namespace App\Interfaces\Console;

use App\Infrastructure\Outbox\EventBridgePublisher;
use Illuminate\Console\Command;

final class RelayOutboxCommand extends Command
{
    protected $signature = 'outbox:relay {--limit=100}';

    protected $description = 'Publish unpublished outbox entries to the platform EventBridge bus.';

    public function handle(EventBridgePublisher $publisher): int
    {
        $count = $publisher->relayBatch((int) $this->option('limit'));
        $this->info("Relayed {$count} event(s).");

        return self::SUCCESS;
    }
}
