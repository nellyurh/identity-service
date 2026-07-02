<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Port\SigningKeyProvider;

/** Read-side: the public verification keys other services fetch to verify tokens offline. */
final readonly class GetJwks
{
    public function __construct(private SigningKeyProvider $keys) {}

    /** @return array{keys:list<array<string,string>>} */
    public function handle(): array
    {
        return ['keys' => $this->keys->jwks()];
    }
}
