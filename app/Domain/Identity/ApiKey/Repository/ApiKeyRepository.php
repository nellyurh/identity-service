<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey\Repository;

use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\Exception\ApiKeyNotFound;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;

interface ApiKeyRepository
{
    /** Look up by the public prefix (the auth hot path). */
    public function findByPrefix(ApiKeyPrefix $prefix): ?ApiKey;

    public function findById(ApiKeyId $id): ?ApiKey;

    /** @throws ApiKeyNotFound */
    public function getById(ApiKeyId $id): ApiKey;

    public function existsByPrefix(ApiKeyPrefix $prefix): bool;

    /** @return list<ApiKey> */
    public function listByOwner(ApiKeyOwner $owner): array;

    /** Persist the aggregate and drain its recorded events to the outbox atomically. */
    public function save(ApiKey $key): void;

    public function nextIdentity(): ApiKeyId;
}
