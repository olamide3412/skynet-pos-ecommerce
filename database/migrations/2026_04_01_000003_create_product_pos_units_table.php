<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_pos_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedBigInteger('pos_item_unit_id')->nullable()->index();
            $table->string('unit_kind')->default('additional'); // 'base' or 'additional'
            $table->string('unit_name');
            $table->decimal('quantity_in_base_units', 15, 4)->default(1);
            $table->decimal('synced_standard_price', 15, 2)->default(0);
            $table->string('online_label')->nullable();
            $table->boolean('is_enabled_online')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamp('last_pos_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_pos_units');
    }
};
