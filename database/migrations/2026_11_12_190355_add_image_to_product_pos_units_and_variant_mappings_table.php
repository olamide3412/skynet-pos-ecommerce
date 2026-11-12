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
        if (Schema::hasTable('product_pos_units') && !Schema::hasColumn('product_pos_units', 'image')) {
            Schema::table('product_pos_units', function (Blueprint $table) {
                $table->string('image')->nullable()->after('online_label');
            });
        }

        if (Schema::hasTable('product_variant_mappings') && !Schema::hasColumn('product_variant_mappings', 'image')) {
            Schema::table('product_variant_mappings', function (Blueprint $table) {
                $table->string('image')->nullable()->after('variant_label');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_pos_units') && Schema::hasColumn('product_pos_units', 'image')) {
            Schema::table('product_pos_units', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }

        if (Schema::hasTable('product_variant_mappings') && Schema::hasColumn('product_variant_mappings', 'image')) {
            Schema::table('product_variant_mappings', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
