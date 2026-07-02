<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('family_id');
            $table->string('token_hash', 64);
            $table->string('access_jti');
            $table->timestampTz('expires_at');
            $table->timestampTz('created_at');
            $table->timestampTz('rotated_at')->nullable();
            $table->ulid('replaced_by')->nullable();
            $table->timestampTz('revoked_at')->nullable();

            $table->unique('token_hash');
            $table->index('user_id');
            $table->index('family_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
