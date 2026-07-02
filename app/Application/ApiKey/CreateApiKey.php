<?php

declare(strict_types=1);

namespace App\Application\ApiKey;

use App\Application\ApiKey\Command\CreateApiKeyCommand;
use App\Application\ApiKey\Result\ApiKeyCreatedResult;
use App\Application\ApiKey\Result\ApiKeyView;
use App\Application\Port\ApiKeyGenerator;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\OwnerType;
use App\Domain\Identity\ApiKey\Repository\ApiKeyRepository;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ApiKey\ValueObject\HashedApiKeySecret;
use App\Domain\Identity\ServiceAccount\Repository\ServiceAccountRepository;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Provision an API key for a user or service account. The owner must exist. Generates the key
 * material, stores only the secret's SHA-256 hash, and returns the full
 * `unero_<env>_<prefix>.<secret>` string exactly once.
 */
final readonly class CreateApiKey
{
    public function __construct(
        private ApiKeyRepository $keys,
        private ApiKeyGenerator $generator,
        private UserRepository $users,
        private ServiceAccountRepository $accounts,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private string $envLabel,
    ) {}

    public function handle(CreateApiKeyCommand $c): ApiKeyCreatedResult
    {
        $owner = new ApiKeyOwner(OwnerType::from($c->ownerType), $c->ownerId);
        $this->assertOwnerExists($owner);

        $scopes = ScopeCollection::fromStrings($c->scopes);
        $expiresAt = $c->expiresAt !== null ? new DateTimeImmutable($c->expiresAt) : null;

        return $this->tx->transactional(function () use ($c, $owner, $scopes, $expiresAt): ApiKeyCreatedResult {
            $material = $this->generator->generate();
            $fullKey = sprintf('unero_%s_%s.%s', $this->envLabel, $material->prefix, $material->secret);

            $key = ApiKey::create(
                $this->keys->nextIdentity(),
                new ApiKeyPrefix($material->prefix),
                HashedApiKeySecret::fromHash(hash('sha256', $material->secret)),
                $c->name,
                $owner,
                $scopes,
                $expiresAt,
                $c->actorId,
                $this->clock->now(),
            );
            $this->keys->save($key);

            $this->audit->record(
                'api_key.created',
                $c->actorId,
                'api_key:'.$key->id->value,
                [],
                ['prefix' => $key->prefix()->value, 'owner_type' => $owner->type->value, 'owner_id' => $owner->id, 'scopes' => $scopes->toArray()],
                $c->requestId,
                null,
            );

            return new ApiKeyCreatedResult(ApiKeyView::fromKey($key), $fullKey);
        });
    }

    private function assertOwnerExists(ApiKeyOwner $owner): void
    {
        if ($owner->type === OwnerType::User) {
            $this->users->getById(new UserId($owner->id));

            return;
        }

        $this->accounts->getById(new ServiceAccountId($owner->id));
    }
}
