<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Override;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** @var array{private:string,public:string}|null Generated once per test run. */
    private static ?array $signingKey = null;

    #[Override]
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        // Every environment (including tests) needs a signing key so token issuance works.
        // Generate one RSA keypair for the whole suite and inject it into config before the
        // SigningKeyProvider singleton resolves. Individual tests may still override it.
        $key = $this->signingKey();
        config([
            'unero.jwt.private_key' => $key['private'],
            'unero.jwt.public_key' => $key['public'],
            'unero.jwt.kid' => 'test-kid',
        ]);

        return $app;
    }

    /** @return array{private:string,public:string} */
    private function signingKey(): array
    {
        if (self::$signingKey !== null) {
            return self::$signingKey;
        }

        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($resource === false) {
            throw new RuntimeException('could not generate test signing key');
        }
        $privatePem = '';
        openssl_pkey_export($resource, $privatePem);
        $details = openssl_pkey_get_details($resource);
        $publicPem = is_array($details) && isset($details['key']) ? (string) $details['key'] : '';

        return self::$signingKey = ['private' => (string) $privatePem, 'public' => $publicPem];
    }
}
