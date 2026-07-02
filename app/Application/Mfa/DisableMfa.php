<?php

declare(strict_types=1);

namespace App\Application\Mfa;

use App\Application\Mfa\Command\DisableMfaCommand;
use App\Application\Mfa\Result\DisableMfaResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Mfa\Repository\RecoveryCodeRepository;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;

/**
 * Turn off a user's TOTP second factor. Idempotent: with no active credential it's a no-op reported
 * as disabled=false. Otherwise the credential is disabled (emitting MFADisabled), recovery codes are
 * cleared, and later logins stop challenging.
 */
final readonly class DisableMfa
{
    public function __construct(
        private TotpCredentialRepository $credentials,
        private RecoveryCodeRepository $recoveryCodes,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(DisableMfaCommand $c): DisableMfaResult
    {
        $userId = new UserId($c->userId);
        $active = $this->credentials->findActiveForUser($userId);
        if (! $active instanceof TotpCredential) {
            return new DisableMfaResult(false);
        }

        return $this->tx->transactional(function () use ($c, $userId, $active): DisableMfaResult {
            $active->disable($this->clock->now());
            $this->credentials->save($active);
            $this->recoveryCodes->deleteForUser($userId);

            $this->audit->record('mfa.disabled', $c->actorId, 'user:'.$c->userId, [], ['method' => 'totp'], $c->requestId, null);

            return new DisableMfaResult(true);
        });
    }
}
