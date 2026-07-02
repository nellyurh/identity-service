<?php

declare(strict_types=1);

namespace App\Domain\Identity\ApiKey;

/** Who an API key belongs to: an end user or a platform service account. */
enum OwnerType: string
{
    case User = 'user';
    case ServiceAccount = 'service_account';
}
