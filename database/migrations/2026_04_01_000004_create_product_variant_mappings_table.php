<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('variant_key')->index(); // e.g. 'color=blue|size=small'
            $table->string('variant_label'); // e.g. 'Blue / Small'
            $table->json('option_values')->nullable(); // ['Color' => 'Blue', 'Size' => 'Small']
            $table->unsignedBigInteger('pos_item_id')->nullable()->index();
            $table->string('inventory_source')->default('pos'); // 'pos' or 'ecommerce'
            $table->integer('local_stock')->default(0);
            $table->decimal('local_price', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('last_pos_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_mappings');
    }
};
