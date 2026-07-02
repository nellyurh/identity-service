<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Application\Port\SigningKeyProvider;
use App\Application\Port\TokenIssuer;
use App\Application\Port\TokenVerifier;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Infrastructure\Audit\DatabaseAuditWriter;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Outbox\EventBridgePublisher;
use App\Infrastructure\Persistence\Repository\EloquentUserRepository;
use App\Infrastructure\Security\ArgonPasswordHasher;
use App\Infrastructure\Token\ConfigSigningKeyProvider;
use App\Infrastructure\Token\OpensslRs256TokenIssuer;
use App\Infrastructure\Token\OpensslRs256TokenVerifier;
use App\Infrastructure\Transaction\LaravelTransactionManager;
use Aws\EventBridge\EventBridgeClient;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

/**
 * Binds Application ports to their Infrastructure adapters (Ports & Adapters). Domain and
 * Application depend only on the interfaces bound here; nothing framework-specific leaks
 * upward. Repositories/adapters are added as each capability lands.
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

        $this->app->singleton(SigningKeyProvider::class, static function (): ConfigSigningKeyProvider {
            /** @var array{private_key:string,public_key:string,kid:string} $jwt */
            $jwt = config('unero.jwt');

            return new ConfigSigningKeyProvider($jwt['private_key'], $jwt['public_key'], $jwt['kid']);
        });

        $this->app->singleton(TokenIssuer::class, static function (Application $app): OpensslRs256TokenIssuer {
            $keys = $app->make(SigningKeyProvider::class);
            assert($keys instanceof SigningKeyProvider);
            /** @var array{issuer:string,audience:string,access_ttl:int} $jwt */
            $jwt = config('unero.jwt');

            return new OpensslRs256TokenIssuer($keys, $jwt['issuer'], $jwt['audience'], $jwt['access_ttl']);
        });

        $this->app->singleton(TokenVerifier::class, static function (Application $app): OpensslRs256TokenVerifier {
            $keys = $app->make(SigningKeyProvider::class);
            assert($keys instanceof SigningKeyProvider);
            /** @var array{issuer:string,audience:string} $jwt */
            $jwt = config('unero.jwt');

            return new OpensslRs256TokenVerifier($keys, $jwt['issuer'], $jwt['audience']);
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
