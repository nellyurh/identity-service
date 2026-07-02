<?php

declare(strict_types=1);

namespace App\Application\ApiKey\Result;

/**
 * The result of a rotation: the replacement key's view + its full `unero_<env>_<prefix>.<secret>`
 * string (shown once), plus the rotated key's id and the instant its grace window ends.
 */
final readonly class ApiKeyRotatedResult
{
    public function __construct(
        public ApiKeyView $replacement,
        public string $fullKey,
        public string $rotatedKeyId,
        public ?string $graceExpiresAt,
    ) {}
}
