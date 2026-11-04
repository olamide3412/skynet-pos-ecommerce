<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('coupons') && !Schema::hasColumn('coupons', 'exclude_sale_items')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->boolean('exclude_sale_items')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'exclude_sale_items')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('exclude_sale_items');
            });
        }
    }
};

