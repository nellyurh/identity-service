<?php

declare(strict_types=1);

namespace App\Application\Mfa;

use App\Application\Mfa\Command\RegenerateRecoveryCodesCommand;
use App\Application\Mfa\Result\RecoveryCodesResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Mfa\Exception\MfaNotEnabled;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\ValueObject\UserId;

/** Issue a fresh batch of recovery codes, invalidating the previous batch. Requires active MFA (MFA_005). */
final readonly class RegenerateRecoveryCodes
{
    public function __construct(
        private TotpCredentialRepository $credentials,
        private GenerateRecoveryCodes $generator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(RegenerateRecoveryCodesCommand $c): RecoveryCodesResult
    {
        $userId = new UserId($c->userId);
        if (! $this->credentials->findActiveForUser($userId) instanceof TotpCredential) {
            throw MfaNotEnabled::withUser($c->userId);
        }

        return $this->tx->transactional(function () use ($c, $userId): RecoveryCodesResult {
            $codes = $this->generator->forUser($userId, $this->clock->now());

            $this->audit->record('mfa.recovery_codes_regenerated', $c->actorId, 'user:'.$c->userId, [], ['count' => count($codes)], $c->requestId, null);

            return new RecoveryCodesResult($codes);
        });
    }
}
