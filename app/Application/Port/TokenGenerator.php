<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Produces the opaque, high-entropy secret handed to the client as a refresh token. The
 * adapter owns the randomness source; callers hash the result (SHA-256) before persisting,
 * so the plaintext exists only in the response and never at rest.
 */
interface TokenGenerator
{
    /** Return a cryptographically random opaque token (URL-safe, non-empty). */
    public function generate(): string;
}
