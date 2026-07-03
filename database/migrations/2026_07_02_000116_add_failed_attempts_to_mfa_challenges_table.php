<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mfa_challenges', function (Blueprint $table): void {
            $table->integer('failed_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('mfa_challenges', function (Blueprint $table): void {
            $table->dropColumn('failed_attempts');
        });
    }
};
