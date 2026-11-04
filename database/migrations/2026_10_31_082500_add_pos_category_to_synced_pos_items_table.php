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
            $table->unsignedBigInteger('pos_category_id')->nullable()->after('pos_item_name');
            $table->string('pos_category_name')->nullable()->after('pos_category_id');
            $table->index('pos_category_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('synced_pos_items', function (Blueprint $table) {
            $table->dropIndex(['pos_category_name']);
            $table->dropColumn(['pos_category_id', 'pos_category_name']);
        });
    }
};

