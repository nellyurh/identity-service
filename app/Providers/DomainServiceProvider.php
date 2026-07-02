<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Infrastructure\Audit\DatabaseAuditWriter;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Outbox\EventBridgePublisher;
use App\Infrastructure\Persistence\Repository\EloquentUserRepository;
use App\Infrastructure\Security\ArgonPasswordHasher;
use App\Infrastructure\Transaction\LaravelTransactionManager;
use Aws\EventBridge\EventBridgeClient;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Binds Application ports to their Infrastructure adapters (Ports & Adapters). Domain and
 * Application depend only on the interfaces bound here; nothing framework-specific leaks
 * upward. Repositories are added here as each aggregate's persistence lands.
 */
final class DomainServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(AuditWriter::class, DatabaseAuditWriter::class);
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->bind(TransactionManager::class, LaravelTransactionManager::class);

        $this->app->bind(UserRepository::class, EloquentUserRepository::class);

        $this->app->singleton(PasswordHasher::class, static function (): ArgonPasswordHasher {
            /** @var array{memory_cost:int,time_cost:int,threads:int} $p */
            $p = config('unero.password');

            return new ArgonPasswordHasher($p['memory_cost'], $p['time_cost'], $p['threads']);
        });

        $this->app->singleton(EventBridgePublisher::class, static fn (): EventBridgePublisher => new EventBridgePublisher(
            new EventBridgeClient([
                'region' => (string) config('unero.aws_region'),
                'version' => 'latest',
            ]),
            (string) config('unero.event_bus'),
        ));
    }
}
