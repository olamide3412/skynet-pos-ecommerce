<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // Core identity
            $table->string('name');
            $table->string('slug')->unique();          // auto-generated from name
            $table->string('url_key')->unique();       // custom SEO URL key (required, unique)
            $table->string('product_number')->nullable()->unique(); // e.g. PRD-001
            $table->string('sku')->nullable()->unique();
            $table->longText('description')->nullable();
            $table->text('short_description')->nullable();

            // Pricing
            $table->decimal('price', 10, 2);           // base / selling price
            $table->decimal('cost_price', 10, 2)->nullable();      // internal cost
            $table->decimal('discount_price', 10, 2)->nullable();  // manual discount price
            $table->decimal('special_price', 10, 2)->nullable();   // time-limited special price
            $table->timestamp('special_price_from')->nullable();
            $table->timestamp('special_price_to')->nullable();

            // Inventory
            $table->integer('stock')->default(0);
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->json('variants')->nullable();
            $table->json('related_products')->nullable();  // manually chosen related product IDs

            // Flags & Visibility
            $table->boolean('status')->default(true);              // Active / Inactive
            $table->boolean('is_new')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('visible_individually')->default(true); // show in lists/search
            $table->boolean('show_stock_level')->default(true);    // show qty on storefront

            // POS Integration & Dual Inventory Source
            $table->string('inventory_source')->default('ecommerce'); // 'pos' or 'ecommerce'
            $table->unsignedBigInteger('pos_item_id')->nullable()->unique();
            $table->string('pos_item_name_snapshot')->nullable();
            $table->string('pos_base_unit_name')->nullable();
            $table->string('pos_sync_status')->default('synced'); // 'synced', 'out_of_sync', 'inactive_pos_item', 'missing_pos_item', 'mapping_pending', 'mapping_sync_failed'
            $table->timestamp('pos_item_updated_at')->nullable();
            $table->timestamp('last_pos_sync_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
