<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_providers', function (Blueprint $table) {
            $table->boolean('enabled')->default(true)->after('configuration');
            $table->integer('frequency_hours')->default(24)->after('enabled');
            $table->integer('retention_days')->default(7)->after('frequency_hours');
            $table->timestamp('last_run_at')->nullable()->after('retention_days');
            $table->dropColumn('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('backup_providers', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
            $table->dropColumn(['enabled', 'frequency_hours', 'retention_days', 'last_run_at']);
        });
    }
};
