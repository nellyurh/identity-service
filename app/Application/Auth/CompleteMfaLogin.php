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
use App\Domain\Identity\Mfa\Repository\MfaChallengeRepository;
use App\Domain\Identity\Mfa\Repository\TotpCredentialRepository;
use App\Domain\Identity\Mfa\TotpCredential;
use App\Domain\Identity\User\Repository\UserRepository;

/**
 * Complete an MFA login: verify the challenge, verify the TOTP code against the user's active
 * credential, consume the challenge, and issue the session. Invalid/expired challenge -> MFA_004;
 * wrong code -> MFA_002. The password was already verified when the challenge was issued.
 */
final readonly class CompleteMfaLogin
{
    public function __construct(
        private MfaChallengeRepository $challenges,
        private TotpCredentialRepository $totpCredentials,
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

        $credential = $this->totpCredentials->findActiveForUser($challenge->userId);
        if (! $credential instanceof TotpCredential) {
            throw MfaChallengeInvalid::create();
        }

        if (! $this->totp->verify($this->cipher->decrypt($credential->encryptedSecret()), $c->code, $now)) {
            $this->audit->record('login.mfa_failed', $challenge->userId->value, 'user:'.$challenge->userId->value, [], [], $c->requestId, null);
            throw TotpCodeInvalid::create();
        }

        $user = $this->users->getById($challenge->userId);

        return $this->tx->transactional(function () use ($c, $challenge, $user, $now): LoginResult {
            $challenge->consume($now);
            $this->challenges->save($challenge);

            $result = $this->session->issue($user->id->value, $user->status()->value, $user->isEmailVerified(), $c->requestId, $now);

            $this->audit->record('login.mfa_succeeded', $user->id->value, 'user:'.$user->id->value, [], [], $c->requestId, null);

            return $result;
        });
    }
}
