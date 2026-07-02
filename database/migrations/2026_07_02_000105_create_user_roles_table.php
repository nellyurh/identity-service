<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Role assignment is tenant-aware from day one (tenant_id '' means platform-global),
        // so multi-tenancy can land later without a destructive migration.
        Schema::create('user_roles', function (Blueprint $table): void {
            $table->ulid('user_id');
            $table->ulid('role_id');
            $table->string('tenant_id')->default('');
            $table->timestampTz('created_at');

            $table->primary(['user_id', 'role_id', 'tenant_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->index(['user_id', 'tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
