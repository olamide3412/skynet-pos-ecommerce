<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->string('variant')->nullable();
            
            // POS Item, Unit & Variant Snapshots
            $table->string('inventory_source')->default('ecommerce'); // 'pos' or 'ecommerce'
            $table->unsignedBigInteger('pos_item_id')->nullable();
            $table->unsignedBigInteger('pos_item_unit_id')->nullable();
            $table->string('unit_name')->nullable();
            $table->decimal('quantity_in_base_units', 15, 4)->default(1);
            $table->decimal('standard_pos_price_snapshot', 15, 2)->nullable();
            $table->decimal('online_unit_price', 15, 2)->nullable();
            $table->string('variant_key')->nullable();
            $table->string('variant_label')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
