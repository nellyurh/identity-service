<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\ApiKey\Result\GeneratedApiKeyMaterial;

/** Generates the random material for a new API key (public prefix + high-entropy secret). */
interface ApiKeyGenerator
{
    public function generate(): GeneratedApiKeyMaterial;
}
