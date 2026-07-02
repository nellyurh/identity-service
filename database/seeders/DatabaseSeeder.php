<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Root seeder. Aggregate seeders (the built-in roles + permission catalog) are registered
 * here as they land in the authorization milestone.
 */
final class DatabaseSeeder extends Seeder
{
    /** @var array<class-string<Seeder>> */
    private const array SEEDERS = [];

    public function run(): void
    {
        $this->call(self::SEEDERS);
    }
}
