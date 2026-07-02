<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Per-user authorization version. Bumped on every role change so cached authorization
            // (including permissions baked into already-issued access tokens) can be detected stale.
            $table->unsignedInteger('authz_version')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('authz_version');
        });
    }
};
