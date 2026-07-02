<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('resource');
            $table->string('action');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->unique('name');
            $table->index('resource');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
