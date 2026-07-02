<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Domain\Identity\Token\Exception\TokenInvalid;
use App\Infrastructure\Token\ConfigSigningKeyProvider;
use App\Infrastructure\Token\Jwt;
use App\Infrastructure\Token\OpensslRs256TokenIssuer;
use App\Infrastructure\Token\OpensslRs256TokenVerifier;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Rotation contract: a verifier configured with a current key AND verify-only keys accepts tokens
 * signed by any of them (selected by kid), publishes them all in JWKS, and rejects unknown kids.
 * This is what makes rotation zero-downtime — the previous key keeps verifying its live tokens.
 */
final class SigningKeyRotationTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    /** @return array{priv:string,pub:string} */
    private function keypair(): array
    {
        $r = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($r === false) {
            throw new RuntimeException('could not generate test key');
        }
        $priv = '';
        openssl_pkey_export($r, $priv);
        $d = openssl_pkey_get_details($r);
        $pub = is_array($d) && isset($d['key']) ? (string) $d['key'] : '';

        return ['priv' => (string) $priv, 'pub' => $pub];
    }

    private function issuer(ConfigSigningKeyProvider $keys): OpensslRs256TokenIssuer
    {
        return new OpensslRs256TokenIssuer($keys, 'unero.identity-service', 'unero-internal', 900);
    }

    public function test_verifier_accepts_current_and_verify_only_keys_and_rejects_unknown(): void
    {
        $a = $this->keypair();
        $b = $this->keypair();
        $c = $this->keypair();

        // Current key is A; B is a previous key still trusted for verification.
        $keys = new ConfigSigningKeyProvider($a['priv'], $a['pub'], 'kid-a', ['kid-b' => $b['pub']]);
        $verifier = new OpensslRs256TokenVerifier($keys, 'unero.identity-service', 'unero-internal');

        $tokenA = $this->issuer($keys)->issueAccessToken('01J000000000000000000USER', [], $this->now);
        $tokenB = $this->issuer(new ConfigSigningKeyProvider($b['priv'], $b['pub'], 'kid-b'))
            ->issueAccessToken('01J000000000000000000USER', [], $this->now);
        $tokenC = $this->issuer(new ConfigSigningKeyProvider($c['priv'], $c['pub'], 'kid-c'))
            ->issueAccessToken('01J000000000000000000USER', [], $this->now);

        // Current and verify-only both pass.
        $this->assertSame('kid-a', $this->kid($tokenA->token));
        $verifier->verify($tokenA->token, $this->now);
        $verifier->verify($tokenB->token, $this->now);

        // Unknown kid is rejected.
        $this->expectException(TokenInvalid::class);
        $verifier->verify($tokenC->token, $this->now);
    }

    public function test_public_key_lookup_by_kid(): void
    {
        $a = $this->keypair();
        $b = $this->keypair();
        $keys = new ConfigSigningKeyProvider($a['priv'], $a['pub'], 'kid-a', ['kid-b' => $b['pub']]);

        $this->assertSame($a['pub'], $keys->publicKeyPemForKid('kid-a'));
        $this->assertSame($b['pub'], $keys->publicKeyPemForKid('kid-b'));
        $this->assertNull($keys->publicKeyPemForKid('kid-x'));
    }

    public function test_jwks_publishes_the_whole_set(): void
    {
        $a = $this->keypair();
        $b = $this->keypair();
        $keys = new ConfigSigningKeyProvider($a['priv'], $a['pub'], 'kid-a', ['kid-b' => $b['pub']]);

        $jwks = $keys->jwks();
        $kids = array_map(static fn (array $k): string => $k['kid'], $jwks);

        $this->assertCount(2, $jwks);
        $this->assertContains('kid-a', $kids);
        $this->assertContains('kid-b', $kids);
    }

    private function kid(string $jwt): string
    {
        $header = Jwt::decodeSegment(explode('.', $jwt)[0]);

        return is_array($header) && is_string($header['kid'] ?? null) ? $header['kid'] : '';
    }
}
