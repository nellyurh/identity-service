<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('action');
            $table->string('actor_id');
            $table->string('target');
            $table->jsonb('before_json');
            $table->jsonb('after_json');
            $table->string('request_id');
            $table->string('reason')->nullable();
            $table->timestampTz('created_at');
            $table->index(['target', 'created_at']);
            $table->index('actor_id');
        });

        // Append-only at the data layer: revoke UPDATE/DELETE for the app role.
        // Guarded so non-pgsql test drivers (sqlite) don't fail.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE RULE audit_events_no_update AS ON UPDATE TO audit_events DO INSTEAD NOTHING');
            DB::statement('CREATE RULE audit_events_no_delete AS ON DELETE TO audit_events DO INSTEAD NOTHING');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP RULE IF EXISTS audit_events_no_update ON audit_events');
            DB::statement('DROP RULE IF EXISTS audit_events_no_delete ON audit_events');
        }
        Schema::dropIfExists('audit_events');
    }
};
