<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synced_pos_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pos_item_id')->unique()->index();
            $table->string('pos_item_name');
            $table->string('barcode')->nullable()->index();
            $table->string('item_reference')->nullable();
            $table->string('sku')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->string('base_unit_name')->default('Piece');
            $table->decimal('standard_price', 15, 2)->default(0);
            $table->decimal('effective_availability', 15, 2)->default(0);
            $table->boolean('imei_tracking_enabled')->default(false);
            $table->string('mapping_status')->default('unmapped'); // 'unmapped', 'mapped_simple', 'mapped_variant', 'ignored'
            $table->timestamp('pos_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('synced_pos_item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('synced_pos_item_id')->constrained('synced_pos_items')->cascadeOnDelete();
            $table->unsignedBigInteger('pos_item_unit_id')->nullable()->index();
            $table->string('unit_kind')->default('additional'); // 'base' or 'additional'
            $table->string('unit_name');
            $table->decimal('quantity_in_base_units', 15, 4)->default(1);
            $table->decimal('standard_price', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synced_pos_item_units');
        Schema::dropIfExists('synced_pos_items');
    }
};
