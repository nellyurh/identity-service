<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Infrastructure\Audit\DatabaseAuditWriter;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Outbox\EventBridgePublisher;
use Aws\EventBridge\EventBridgeClient;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Binds Application ports to their Infrastructure adapters (Ports & Adapters). Domain and
 * Application depend only on the interfaces bound here; nothing framework-specific leaks
 * upward. Aggregate repositories are bound in later milestones as each aggregate lands.
 */
final class DomainServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(AuditWriter::class, DatabaseAuditWriter::class);
        $this->app->singleton(Clock::class, SystemClock::class);

        $this->app->singleton(EventBridgePublisher::class, static fn (): EventBridgePublisher => new EventBridgePublisher(
            new EventBridgeClient([
                'region' => (string) config('unero.aws_region'),
                'version' => 'latest',
            ]),
            (string) config('unero.event_bus'),
        ));
    }
}
