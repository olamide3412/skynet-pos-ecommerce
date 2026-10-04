<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // 'connection_test', 'catalog_sync', 'availability_sync', 'reservation_create', etc.
            $table->string('status')->index(); // 'started', 'success', 'failed', 'warning'
            $table->string('reference')->nullable()->index();
            $table->text('message')->nullable();
            $table->json('context')->nullable(); // sanitized payload / details (no secrets)
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sync_logs');
    }
};
