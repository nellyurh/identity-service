<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('email');
            $table->string('username');
            $table->text('password_hash');
            $table->string('status');
            $table->timestampTz('email_verified_at')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique('email');
            $table->unique('username');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
