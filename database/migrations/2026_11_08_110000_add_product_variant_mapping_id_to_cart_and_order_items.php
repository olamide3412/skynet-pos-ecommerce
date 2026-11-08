<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cart_items') && !Schema::hasColumn('cart_items', 'product_variant_mapping_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreignId('product_variant_mapping_id')
                    ->nullable()
                    ->after('product_pos_unit_id')
                    ->constrained('product_variant_mappings')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'product_variant_mapping_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreignId('product_variant_mapping_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_variant_mappings')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cart_items') && Schema::hasColumn('cart_items', 'product_variant_mapping_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropForeign(['product_variant_mapping_id']);
                $table->dropColumn('product_variant_mapping_id');
            });
        }

        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'product_variant_mapping_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_variant_mapping_id']);
                $table->dropColumn('product_variant_mapping_id');
            });
        }
    }
};

