<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->string('status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            // Billing info (captured at checkout)
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_address')->nullable();
            $table->string('delivery_method')->nullable()->default('delivery');
            
            // POS Inventory Reservation & Synchronization
            $table->string('inventory_reference')->nullable()->unique()->index();
            $table->string('pos_reservation_status')->default('none'); // 'none', 'reserved', 'confirmed', 'released', 'expired'
            $table->timestamp('pos_reservation_expires_at')->nullable();
            $table->timestamp('pos_reservation_confirmed_at')->nullable();
            $table->timestamp('pos_reservation_released_at')->nullable();
            $table->text('pos_sync_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
