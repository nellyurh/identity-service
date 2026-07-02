<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('prefix')->unique();     // public, indexed — O(1) lookup by prefix
            $table->text('secret_hash');            // sha256 of the secret portion; shown once
            $table->string('name');
            $table->string('owner_type');           // user | service_account
            $table->ulid('owner_id');
            $table->jsonb('scopes');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->string('created_by');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
