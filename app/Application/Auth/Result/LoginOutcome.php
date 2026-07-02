<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

/**
 * The result of a login attempt: either a fully-issued session, or (when the user has MFA) a
 * challenge that must be completed with a second factor before any tokens are issued.
 */
final readonly class LoginOutcome
{
    private function __construct(
        public ?LoginResult $session,
        public ?MfaChallengeIssued $challenge,
    ) {}

    public static function authenticated(LoginResult $session): self
    {
        return new self($session, null);
    }

    public static function mfaRequired(MfaChallengeIssued $challenge): self
    {
        return new self(null, $challenge);
    }

    public function requiresMfa(): bool
    {
        return $this->challenge instanceof MfaChallengeIssued;
    }
}
