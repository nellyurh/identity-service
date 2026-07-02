<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('secret_hash');
            $table->string('status');
            $table->jsonb('scopes');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_accounts');
    }
};
