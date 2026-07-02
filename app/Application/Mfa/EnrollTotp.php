<?php

declare(strict_types=1);

namespace App\Application\Mfa;

use App\Application\Mfa\Command\EnrollTotpCommand;
use App\Application\Mfa\Result\EnrollTotpResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\SecretCipher;
use App\Application\Port\TotpProvider;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Mfa\Exception\MfaAlreadyEnabled;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\UserId;

/**
 * Begin TOTP enrollment: generate a secret, store it encrypted in a pending credential, and return
 * the secret + otpauth URI once so the user can key it into an authenticator. Any prior pending
 * enrollment is superseded. Fails if MFA is already active (MFA_001).
 */
final readonly class EnrollTotp
{
    public function __construct(
        private TotpCredentialRepository $credentials,
        private TotpProvider $totp,
        private SecretCipher $cipher,
        private UserRepository $users,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(EnrollTotpCommand $c): EnrollTotpResult
    {
        $user = $this->users->getById(new UserId($c->userId));

        if ($this->credentials->findActiveForUser($user->id) instanceof TotpCredential) {
            throw MfaAlreadyEnabled::withUser($user->id->value);
        }

        $secret = $this->totp->generateSecret();
        $uri = $this->totp->provisioningUri($secret, $user->email()->value);

        return $this->tx->transactional(function () use ($c, $user, $secret, $uri): EnrollTotpResult {
            $this->credentials->deleteForUser($user->id);
            $this->credentials->save(TotpCredential::enroll(
                $this->credentials->nextIdentity(),
                $user->id,
                $this->cipher->encrypt($secret),
                $this->clock->now(),
            ));

            $this->audit->record('mfa.totp_enroll_started', $c->actorId, 'user:'.$user->id->value, [], ['method' => 'totp'], $c->requestId, null);

            return new EnrollTotpResult($secret, $uri);
        });
    }
}
