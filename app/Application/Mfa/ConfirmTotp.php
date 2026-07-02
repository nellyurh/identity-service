<?php

declare(strict_types=1);

namespace App\Application\Mfa;

use App\Application\Mfa\Command\ConfirmTotpCommand;
use App\Application\Mfa\Result\ConfirmTotpResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\SecretCipher;
use App\Application\Port\TotpProvider;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Mfa\Exception\MfaNotEnrolled;
use App\Domain\Identity\Mfa\Exception\TotpCodeInvalid;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;

/**
 * Confirm TOTP enrollment: verify a code against the pending credential's (decrypted) secret and, on
 * success, activate it (emitting MFAEnabled). No pending enrollment -> MFA_003; wrong code -> MFA_002.
 */
final readonly class ConfirmTotp
{
    public function __construct(
        private TotpCredentialRepository $credentials,
        private TotpProvider $totp,
        private SecretCipher $cipher,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(ConfirmTotpCommand $c): ConfirmTotpResult
    {
        $userId = new UserId($c->userId);
        $pending = $this->credentials->findPendingForUser($userId);

        if (! $pending instanceof TotpCredential) {
            throw MfaNotEnrolled::withUser($userId->value);
        }

        $now = $this->clock->now();
        if (! $this->totp->verify($this->cipher->decrypt($pending->encryptedSecret()), $c->code, $now)) {
            throw TotpCodeInvalid::create();
        }

        return $this->tx->transactional(function () use ($c, $pending, $userId, $now): ConfirmTotpResult {
            $pending->confirm($now);
            $this->credentials->save($pending);

            $this->audit->record('mfa.totp_enabled', $c->actorId, 'user:'.$userId->value, [], ['method' => 'totp'], $c->requestId, null);

            return new ConfirmTotpResult(true);
        });
    }
}
