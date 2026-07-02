<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use RuntimeException;
use Tests\TestCase;

/**
 * When verify-only keys are configured (a rotation in progress), the public JWKS endpoint
 * publishes the current key AND the previous ones, so verifiers can validate tokens signed by
 * either. The base TestCase installs the current key (kid "test-kid"); here we add one more.
 */
final class JwksRotationTest extends TestCase
{
    public function test_jwks_publishes_current_plus_verify_only_keys(): void
    {
        $r = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($r === false) {
            throw new RuntimeException('could not generate test key');
        }
        $details = openssl_pkey_get_details($r);
        $oldPublic = is_array($details) && isset($details['key']) ? (string) $details['key'] : '';

        config(['unero.jwt.verify_only_public_keys' => json_encode(['kid-old' => $oldPublic])]);

        $response = $this->getJson('/.well-known/jwks.json')->assertOk();

        $kids = [];
        foreach ((array) $response->json('keys') as $key) {
            if (is_array($key) && isset($key['kid'])) {
                $kids[] = (string) $key['kid'];
            }
        }

        $this->assertContains('test-kid', $kids);
        $this->assertContains('kid-old', $kids);
        $this->assertCount(2, $kids);
    }
}
