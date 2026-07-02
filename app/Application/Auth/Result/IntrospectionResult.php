<?php

declare(strict_types=1);

namespace App\Application\Auth\Result;

/**
 * The outcome of introspecting an access token (RFC 7662 shape). `active` is the only field a
 * caller must check; the rest are populated only for a live, non-revoked token. A token that
 * fails verification OR is on the jti denylist is reported inactive with nothing else disclosed.
 */
final readonly class IntrospectionResult
{
    /** @param list<string> $permissions */
    private function __construct(
        public bool $active,
        public ?string $subject,
        public ?string $jti,
        public ?string $tokenUse,
        public ?string $expiresAt,
        public array $permissions,
        public ?int $authzVersion,
    ) {}

    public static function inactive(): self
    {
        return new self(false, null, null, null, null, [], null);
    }

    /** @param list<string> $permissions */
    public static function active(string $subject, string $jti, ?string $tokenUse, string $expiresAt, array $permissions, ?int $authzVersion): self
    {
        return new self(true, $subject, $jti, $tokenUse, $expiresAt, $permissions, $authzVersion);
    }
}
