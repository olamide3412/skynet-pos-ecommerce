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
        Schema::table('product_pos_units', function (Blueprint $table) {
            $table->boolean('show_in_listing')->default(true)->after('is_enabled_online');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_pos_units', function (Blueprint $table) {
            $table->dropColumn('show_in_listing');
        });
    }
};
