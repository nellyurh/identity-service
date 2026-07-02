<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_resets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            // Opaque correlation id carried in the PasswordResetRequested event; the notification
            // service exchanges it (authenticated) for the freshly-minted token. Not a secret.
            $table->string('delivery_ref')->unique();
            // sha256 of the raw token — set only at materialisation (the token is minted then, so the
            // raw value never exists at request time and never enters an event).
            $table->string('token_hash')->nullable()->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('materialized_at')->nullable();
            $table->timestampTz('used_at')->nullable();
            $table->timestampTz('created_at');

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
    }
};
