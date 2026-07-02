<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Port\PasswordHasher;

/**
 * Argon2id password hashing (never bcrypt). Parameters come from config so they can be
 * tuned per environment without touching code; verify is constant-time via password_verify,
 * and needsRehash lets the platform raise cost over time.
 */
final readonly class ArgonPasswordHasher implements PasswordHasher
{
    /** @var array{memory_cost:int,time_cost:int,threads:int} */
    private array $options;

    public function __construct(int $memoryCost, int $timeCost, int $threads)
    {
        $this->options = ['memory_cost' => $memoryCost, 'time_cost' => $timeCost, 'threads' => $threads];
    }

    public function hash(string $plainText): string
    {
        return password_hash($plainText, PASSWORD_ARGON2ID, $this->options);
    }

    public function verify(string $plainText, string $hash): bool
    {
        return password_verify($plainText, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }
}
