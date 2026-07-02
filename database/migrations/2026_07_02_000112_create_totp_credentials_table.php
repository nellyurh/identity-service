<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('totp_credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->unique();  // at most one TOTP credential per user
            $table->text('secret');             // encrypted at rest (SecretCipher); never plaintext
            $table->string('status')->default('pending');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('totp_credentials');
    }
};
