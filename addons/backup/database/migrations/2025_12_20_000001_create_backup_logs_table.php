<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('backup_providers')->cascadeOnDelete();
            $table->string('identifier')->nullable();
            $table->enum('status', ['success', 'failed', 'running'])->default('running');
            $table->string('type')->nullable(); // full, database, files
            $table->bigInteger('size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['provider_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
