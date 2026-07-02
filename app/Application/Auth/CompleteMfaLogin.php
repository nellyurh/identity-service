<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Auth\Command\CompleteMfaLoginCommand;
use App\Application\Auth\Result\LoginResult;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\SecretCipher;
use App\Application\Port\TotpProvider;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\Mfa\Exception\MfaChallengeInvalid;
use App\Domain\Identity\Mfa\Exception\TotpCodeInvalid;
use App\Domain\Identity\Mfa\MfaChallenge;
use App\Domain\Identity\Mfa\RecoveryCode;
use App\Domain\Identity\Mfa\Repository\MfaChallengeRepository;
use App\Domain\Identity\Mfa\Repository\RecoveryCodeRepository;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\Repository\UserRepository;
use DateTimeImmutable;

/**
 * Complete an MFA login: verify the challenge, then verify the second factor — either a TOTP code
 * against the active credential, or a one-time recovery code — before consuming the challenge and
 * issuing the session. A recovery code is consumed single-use. Invalid/expired challenge -> MFA_004;
 * wrong code / spent-or-unknown recovery code -> MFA_002.
 */
final readonly class CompleteMfaLogin
{
    public function __construct(
        private MfaChallengeRepository $challenges,
        private TotpCredentialRepository $totpCredentials,
        private RecoveryCodeRepository $recoveryCodes,
        private TotpProvider $totp,
        private SecretCipher $cipher,
        private IssueUserSession $session,
        private UserRepository $users,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(CompleteMfaLoginCommand $c): LoginResult
    {
        $now = $this->clock->now();

        $challenge = $this->challenges->findByHash(hash('sha256', $c->challengeToken));
        if (! $challenge instanceof MfaChallenge || ! $challenge->isUsable($now)) {
            throw MfaChallengeInvalid::create();
        }

        $recoveryCode = null;
        if ($c->recoveryCode !== null) {
            $recoveryCode = $this->verifyRecoveryCode($challenge, $c->recoveryCode, $c->requestId);
        } else {
            $this->verifyTotp($challenge, (string) $c->code, $now, $c->requestId);
        }

        $user = $this->users->getById($challenge->userId);

        return $this->tx->transactional(function () use ($c, $challenge, $recoveryCode, $user, $now): LoginResult {
            $challenge->consume($now);
            $this->challenges->save($challenge);

            if ($recoveryCode instanceof RecoveryCode) {
                $recoveryCode->consume($now);
                $this->recoveryCodes->save($recoveryCode);
            }

            $result = $this->session->issue($user->id->value, $user->status()->value, $user->isEmailVerified(), $c->requestId, $now);

            $this->audit->record('login.mfa_succeeded', $user->id->value, 'user:'.$user->id->value, [], [], $c->requestId, null);

            return $result;
        });
    }

    /** Returns the recovery code to consume once the login succeeds. */
    private function verifyRecoveryCode(MfaChallenge $challenge, string $submitted, string $requestId): RecoveryCode
    {
        $hash = hash('sha256', $this->normalize($submitted));
        $code = $this->recoveryCodes->findByHashForUser($challenge->userId, $hash);

        if (! $code instanceof RecoveryCode || ! $code->isUsable()) {
            $this->audit->record('login.mfa_failed', $challenge->userId->value, 'user:'.$challenge->userId->value, [], ['factor' => 'recovery_code'], $requestId, null);
            throw TotpCodeInvalid::create();
        }

        return $code;
    }

    private function verifyTotp(MfaChallenge $challenge, string $code, DateTimeImmutable $now, string $requestId): void
    {
        $credential = $this->totpCredentials->findActiveForUser($challenge->userId);
        if (! $credential instanceof TotpCredential) {
            throw MfaChallengeInvalid::create();
        }

        if (! $this->totp->verify($this->cipher->decrypt($credential->encryptedSecret()), $code, $now)) {
            $this->audit->record('login.mfa_failed', $challenge->userId->value, 'user:'.$challenge->userId->value, [], ['factor' => 'totp'], $requestId, null);
            throw TotpCodeInvalid::create();
        }
    }

    private function normalize(string $code): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}
