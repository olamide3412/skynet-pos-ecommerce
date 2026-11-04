<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('synced_pos_items', 'buying_price')) {
            Schema::table('synced_pos_items', function (Blueprint $table) {
                $table->decimal('buying_price', 15, 2)->default(0)->after('standard_price');
            });
        }

        if (!Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->decimal('cost_price', 15, 2)->default(0)->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('synced_pos_items', 'buying_price')) {
            Schema::table('synced_pos_items', function (Blueprint $table) {
                $table->dropColumn('buying_price');
            });
        }

        if (Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};

