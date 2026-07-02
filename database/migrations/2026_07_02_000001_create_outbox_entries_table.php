<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_type');
            $table->unsignedInteger('event_version');
            $table->string('schema_version');
            $table->string('aggregate_type');
            $table->string('aggregate_id');
            $table->jsonb('payload_json');
            $table->string('correlation_id')->nullable();
            $table->string('causation_id')->nullable();
            $table->timestampTz('created_at');
            $table->timestampTz('published_at')->nullable();
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_entries');
    }
};
