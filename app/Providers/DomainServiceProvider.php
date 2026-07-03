<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\ApiKey\CreateApiKey;
use App\Application\ApiKey\RotateApiKey;
use App\Application\Auth\CompleteMfaLogin;
use App\Application\Auth\IssueUserSession;
use App\Application\Auth\LoginUser;
use App\Application\Auth\LogoutUser;
use App\Application\Auth\RefreshTokens;
use App\Application\EmailVerification\RequestEmailVerification;
use App\Application\Mfa\GenerateRecoveryCodes;
use App\Application\PasswordReset\RequestPasswordReset;
use App\Application\Port\ApiKeyGenerator;
use App\Application\Port\AuditWriter;
use App\Application\Port\AuthorizationResolver;
use App\Application\Port\Clock;
use App\Application\Port\PasswordHasher;
use App\Application\Port\RateLimiter;
use App\Application\Port\SecretCipher;
use App\Application\Port\SigningKeyProvider;
use App\Application\Port\TokenBlacklist;
use App\Application\Port\TokenGenerator;
use App\Application\Port\TokenIssuer;
use App\Application\Port\TokenVerifier;
use App\Application\Port\TotpProvider;
use App\Application\Port\TransactionManager;
use App\Application\User\AuthenticateUser;
use App\Domain\Identity\ApiKey\Repository\ApiKeyRepository;
use App\Domain\Identity\EmailVerification\Repository\EmailVerificationTokenRepository;
use App\Domain\Identity\Mfa\Repository\MfaChallengeRepository;
use App\Domain\Identity\Mfa\Repository\RecoveryCodeRepository;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\PasswordReset\Repository\PasswordResetRepository;
use App\Domain\Identity\Permission\Repository\PermissionRepository;
use App\Domain\Identity\Role\Repository\RoleRepository;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\Token\Repository\RefreshTokenRepository;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Infrastructure\ApiKey\RandomApiKeyGenerator;
use App\Infrastructure\Audit\DatabaseAuditWriter;
use App\Infrastructure\Authorization\EloquentAuthorizationResolver;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Mfa\LaravelSecretCipher;
use App\Infrastructure\Mfa\Rfc6238TotpProvider;
use App\Infrastructure\Outbox\EventBridgePublisher;
use App\Infrastructure\Persistence\Repository\EloquentApiKeyRepository;
use App\Infrastructure\Persistence\Repository\EloquentEmailVerificationTokenRepository;
use App\Infrastructure\Persistence\Repository\EloquentMfaChallengeRepository;
use App\Infrastructure\Persistence\Repository\EloquentPasswordResetRepository;
use App\Infrastructure\Persistence\Repository\EloquentPermissionRepository;
use App\Infrastructure\Persistence\Repository\EloquentRecoveryCodeRepository;
use App\Infrastructure\Persistence\Repository\EloquentRefreshTokenRepository;
use App\Infrastructure\Persistence\Repository\EloquentRoleRepository;
use App\Infrastructure\Persistence\Repository\EloquentServiceAccountRepository;
use App\Infrastructure\Persistence\Repository\EloquentTotpCredentialRepository;
use App\Infrastructure\Persistence\Repository\EloquentUserRepository;
use App\Infrastructure\RateLimit\LaravelRateLimiter;
use App\Infrastructure\Security\ArgonPasswordHasher;
use App\Infrastructure\Token\CacheTokenBlacklist;
use App\Infrastructure\Token\ConfigSigningKeyProvider;
use App\Infrastructure\Token\OpensslRs256TokenIssuer;
use App\Infrastructure\Token\OpensslRs256TokenVerifier;
use App\Infrastructure\Token\RandomRefreshTokenGenerator;
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
        $this->app->bind(PermissionRepository::class, EloquentPermissionRepository::class);
        $this->app->bind(RoleRepository::class, EloquentRoleRepository::class);
        $this->app->bind(AuthorizationResolver::class, EloquentAuthorizationResolver::class);
        $this->app->bind(ServiceAccountRepository::class, EloquentServiceAccountRepository::class);
        $this->app->bind(ApiKeyRepository::class, EloquentApiKeyRepository::class);
        $this->app->bind(ApiKeyGenerator::class, RandomApiKeyGenerator::class);
        $this->app->bind(EmailVerificationTokenRepository::class, EloquentEmailVerificationTokenRepository::class);
        $this->app->bind(PasswordResetRepository::class, EloquentPasswordResetRepository::class);
        $this->app->bind(TotpCredentialRepository::class, EloquentTotpCredentialRepository::class);
        $this->app->bind(MfaChallengeRepository::class, EloquentMfaChallengeRepository::class);
        $this->app->bind(RecoveryCodeRepository::class, EloquentRecoveryCodeRepository::class);
        $this->app->bind(SecretCipher::class, LaravelSecretCipher::class);
        $this->app->bind(RateLimiter::class, LaravelRateLimiter::class);
        $this->app->bind(RefreshTokenRepository::class, EloquentRefreshTokenRepository::class);
        $this->app->bind(TokenGenerator::class, RandomRefreshTokenGenerator::class);
        $this->app->bind(TokenBlacklist::class, CacheTokenBlacklist::class);

        $this->app->singleton(PasswordHasher::class, static function (): ArgonPasswordHasher {
            /** @var array{memory_cost:int,time_cost:int,threads:int} $p */
            $p = config('unero.password');

            return new ArgonPasswordHasher($p['memory_cost'], $p['time_cost'], $p['threads']);
        });

        $this->app->singleton(SigningKeyProvider::class, static function (): ConfigSigningKeyProvider {
            /** @var array{private_key:string,public_key:string,kid:string,verify_only_public_keys:string} $jwt */
            $jwt = config('unero.jwt');

            $verifyOnly = [];
            $decoded = $jwt['verify_only_public_keys'] !== '' ? json_decode($jwt['verify_only_public_keys'], true) : [];
            if (is_array($decoded)) {
                foreach ($decoded as $kid => $pem) {
                    if (is_string($kid) && is_string($pem)) {
                        $verifyOnly[$kid] = $pem;
                    }
                }
            }

            return new ConfigSigningKeyProvider($jwt['private_key'], $jwt['public_key'], $jwt['kid'], $verifyOnly);
        });

        $this->app->singleton(TokenIssuer::class, static function (Application $app): OpensslRs256TokenIssuer {
            $keys = $app->make(SigningKeyProvider::class);
            /** @var array{issuer:string,audience:string,access_ttl:int} $jwt */
            $jwt = config('unero.jwt');

            return new OpensslRs256TokenIssuer($keys, $jwt['issuer'], $jwt['audience'], $jwt['access_ttl']);
        });

        $this->app->singleton(TokenVerifier::class, static function (Application $app): OpensslRs256TokenVerifier {
            $keys = $app->make(SigningKeyProvider::class);
            /** @var array{issuer:string,audience:string} $jwt */
            $jwt = config('unero.jwt');

            return new OpensslRs256TokenVerifier($keys, $jwt['issuer'], $jwt['audience']);
        });

        // Application services that need the refresh TTL scalar (the container cannot autowire
        // an int) are constructed explicitly; the rest autowire from the ports above.
        $this->app->bind(IssueUserSession::class, static function (Application $app): IssueUserSession {
            /** @var array{refresh_ttl:int} $jwt */
            $jwt = config('unero.jwt');

            return new IssueUserSession(
                $app->make(TokenIssuer::class),
                $app->make(RefreshTokenRepository::class),
                $app->make(TokenGenerator::class),
                $app->make(AuditWriter::class),
                $app->make(AuthorizationResolver::class),
                $jwt['refresh_ttl'],
            );
        });

        $this->app->bind(AuthenticateUser::class, static function (Application $app): AuthenticateUser {
            /** @var array{max_attempts:int,duration:int} $lockout */
            $lockout = config('unero.lockout');

            return new AuthenticateUser(
                $app->make(UserRepository::class),
                $app->make(PasswordHasher::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $lockout['max_attempts'],
                $lockout['duration'],
            );
        });

        $this->app->bind(LoginUser::class, static function (Application $app): LoginUser {
            /** @var array{challenge_ttl:int} $mfa */
            $mfa = config('unero.mfa');

            return new LoginUser(
                $app->make(AuthenticateUser::class),
                $app->make(IssueUserSession::class),
                $app->make(TotpCredentialRepository::class),
                $app->make(MfaChallengeRepository::class),
                $app->make(TokenGenerator::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $mfa['challenge_ttl'],
            );
        });

        $this->app->bind(GenerateRecoveryCodes::class, static function (Application $app): GenerateRecoveryCodes {
            /** @var array{recovery_code_count:int} $mfa */
            $mfa = config('unero.mfa');

            return new GenerateRecoveryCodes($app->make(RecoveryCodeRepository::class), $mfa['recovery_code_count']);
        });

        $this->app->bind(CompleteMfaLogin::class, static function (Application $app): CompleteMfaLogin {
            /** @var array{max_challenge_attempts:int} $mfa */
            $mfa = config('unero.mfa');

            return new CompleteMfaLogin(
                $app->make(MfaChallengeRepository::class),
                $app->make(TotpCredentialRepository::class),
                $app->make(RecoveryCodeRepository::class),
                $app->make(TotpProvider::class),
                $app->make(SecretCipher::class),
                $app->make(IssueUserSession::class),
                $app->make(UserRepository::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $mfa['max_challenge_attempts'],
            );
        });

        $this->app->bind(RefreshTokens::class, static function (Application $app): RefreshTokens {
            /** @var array{refresh_ttl:int,access_ttl:int} $jwt */
            $jwt = config('unero.jwt');

            return new RefreshTokens(
                $app->make(TokenIssuer::class),
                $app->make(RefreshTokenRepository::class),
                $app->make(TokenGenerator::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $app->make(TokenBlacklist::class),
                $app->make(AuthorizationResolver::class),
                $jwt['refresh_ttl'],
                $jwt['access_ttl'],
            );
        });

        $this->app->bind(LogoutUser::class, static function (Application $app): LogoutUser {
            /** @var array{access_ttl:int} $jwt */
            $jwt = config('unero.jwt');

            return new LogoutUser(
                $app->make(RefreshTokenRepository::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $app->make(TokenBlacklist::class),
                $jwt['access_ttl'],
            );
        });

        $this->app->bind(CreateApiKey::class, static function (Application $app): CreateApiKey {
            /** @var array{env:string} $apiKey */
            $apiKey = config('unero.api_key');

            return new CreateApiKey(
                $app->make(ApiKeyRepository::class),
                $app->make(ApiKeyGenerator::class),
                $app->make(UserRepository::class),
                $app->make(ServiceAccountRepository::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $apiKey['env'],
            );
        });

        $this->app->bind(RotateApiKey::class, static function (Application $app): RotateApiKey {
            /** @var array{env:string,rotation_grace:int} $apiKey */
            $apiKey = config('unero.api_key');

            return new RotateApiKey(
                $app->make(ApiKeyRepository::class),
                $app->make(ApiKeyGenerator::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $apiKey['env'],
                $apiKey['rotation_grace'],
            );
        });

        $this->app->bind(RequestEmailVerification::class, static function (Application $app): RequestEmailVerification {
            /** @var array{ttl:int} $verification */
            $verification = config('unero.email_verification');

            return new RequestEmailVerification(
                $app->make(UserRepository::class),
                $app->make(EmailVerificationTokenRepository::class),
                $app->make(TokenGenerator::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $verification['ttl'],
            );
        });

        $this->app->bind(RequestPasswordReset::class, static function (Application $app): RequestPasswordReset {
            /** @var array{ttl:int} $reset */
            $reset = config('unero.password_reset');

            return new RequestPasswordReset(
                $app->make(UserRepository::class),
                $app->make(PasswordResetRepository::class),
                $app->make(TokenGenerator::class),
                $app->make(AuditWriter::class),
                $app->make(Clock::class),
                $app->make(TransactionManager::class),
                $reset['ttl'],
            );
        });

        $this->app->bind(TotpProvider::class, static function (Application $app): TotpProvider {
            /** @var array{issuer:string,period:int,digits:int,window:int} $mfa */
            $mfa = config('unero.mfa');

            return new Rfc6238TotpProvider($mfa['issuer'], $mfa['period'], $mfa['digits'], $mfa['window']);
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
