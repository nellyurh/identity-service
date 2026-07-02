<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\Exception\ApiKeyNotFound;
use App\Domain\Identity\ApiKey\OwnerType;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ApiKey\ValueObject\HashedApiKeySecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentApiKeyRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentApiKeyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentApiKeyRepository $keys;

    private DateTimeImmutable $now;

    private ApiKeyOwner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keys = new EloquentApiKeyRepository(new OutboxWriter);
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
        $this->owner = new ApiKeyOwner(OwnerType::ServiceAccount, (string) new Ulid);
    }

    private function newKey(string $prefix, string $secret = 'secret'): ApiKey
    {
        return ApiKey::create(
            $this->keys->nextIdentity(),
            new ApiKeyPrefix($prefix),
            HashedApiKeySecret::fromHash(hash('sha256', $secret)),
            'CI key',
            $this->owner,
            ScopeCollection::fromStrings(['wallet.read', 'wallet.credit']),
            null,
            'admin-1',
            $this->now,
        );
    }

    public function test_save_persists_and_emits_created_and_round_trips(): void
    {
        $key = $this->newKey('ab12cd34ef56', 'the-secret');
        $this->keys->save($key);

        $this->assertDatabaseHas('api_keys', ['id' => $key->id->value, 'prefix' => 'ab12cd34ef56', 'owner_type' => 'service_account']);
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'ApiKeyCreated', 'aggregate_type' => 'ApiKey']);

        $loaded = $this->keys->findByPrefix(new ApiKeyPrefix('ab12cd34ef56'));
        $this->assertNotNull($loaded);
        $this->assertSame(['wallet.read', 'wallet.credit'], $loaded?->scopes()->toArray());
        $this->assertTrue($loaded?->verifySecret('the-secret'));
        $this->assertFalse($loaded?->verifySecret('nope'));
    }

    public function test_list_by_owner_and_revoke(): void
    {
        $this->keys->save($this->newKey('aaa111bbb222'));
        $second = $this->newKey('ccc333ddd444');
        $this->keys->save($second);

        $listed = $this->keys->listByOwner($this->owner);
        $this->assertCount(2, $listed);

        $second->revoke($this->now);
        $this->keys->save($second);

        $reloaded = $this->keys->getById(new ApiKeyId($second->id->value));
        $this->assertTrue($reloaded->isRevoked());
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'ApiKeyRevoked']);
    }

    public function test_exists_by_prefix_and_missing_throws(): void
    {
        $this->keys->save($this->newKey('eee555fff666'));
        $this->assertTrue($this->keys->existsByPrefix(new ApiKeyPrefix('eee555fff666')));

        $this->expectException(ApiKeyNotFound::class);
        $this->keys->getById($this->keys->nextIdentity());
    }
}
