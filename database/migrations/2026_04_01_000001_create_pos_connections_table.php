<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_connections', function (Blueprint $table) {
            $table->id();
            $table->string('pos_base_url');
            $table->string('pos_integration_id')->nullable();
            $table->unsignedBigInteger('pos_branch_id');
            $table->string('pos_branch_name');
            $table->string('client_id');
            $table->text('encrypted_api_secret');
            $table->boolean('is_connected')->default(false);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_successful_request_at')->nullable();
            $table->timestamp('last_catalog_sync_at')->nullable();
            $table->timestamp('last_inventory_sync_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_connections');
    }
};
