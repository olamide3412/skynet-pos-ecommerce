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
        Schema::table('synced_pos_items', function (Blueprint $table) {
            $table->unsignedBigInteger('pos_brand_id')->nullable()->after('pos_category_name');
            $table->string('pos_brand_name')->nullable()->after('pos_brand_id');
            $table->index('pos_brand_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synced_pos_items', function (Blueprint $table) {
            $table->dropIndex(['pos_brand_name']);
            $table->dropColumn(['pos_brand_id', 'pos_brand_name']);
        });
    }
};

