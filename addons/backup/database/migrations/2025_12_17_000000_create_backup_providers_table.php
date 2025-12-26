<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('backup_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver'); // local, ftp, sftp, s3, google...
            $table->text('configuration'); // encrypted json
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_providers');
    }
};
