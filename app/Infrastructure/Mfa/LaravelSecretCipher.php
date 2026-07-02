<?php

declare(strict_types=1);

namespace App\Infrastructure\Mfa;

use App\Application\Port\SecretCipher;
use Illuminate\Contracts\Encryption\Encrypter;

/**
 * SecretCipher backed by Laravel's authenticated encrypter (AES-256, keyed by APP_KEY / KMS). Uses
 * the Encrypter contract (autowirable, aliased to 'encrypter'); the payload is base64 JSON with a MAC.
 */
final readonly class LaravelSecretCipher implements SecretCipher
{
    public function __construct(
        private Encrypter $encrypter,
    ) {}

    public function encrypt(string $plaintext): string
    {
        return $this->encrypter->encrypt($plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        $value = $this->encrypter->decrypt($ciphertext);

        return is_string($value) ? $value : '';
    }
}
