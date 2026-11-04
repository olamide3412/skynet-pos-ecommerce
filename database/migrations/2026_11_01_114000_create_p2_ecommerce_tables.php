<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add is_default to addresses table
        if (Schema::hasTable('addresses') && !Schema::hasColumn('addresses', 'is_default')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('country');
            });
        }

        // 2. Add discount and coupon columns to orders table
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'coupon_code')) {
                    $table->string('coupon_code')->nullable()->after('total_amount');
                }
                if (!Schema::hasColumn('orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0.00)->after('coupon_code');
                }
            });
        }

        // 3. Create coupons table
        if (!Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('type')->default('percent'); // percent, fixed
                $table->decimal('value', 12, 2);
                $table->decimal('min_order_amount', 12, 2)->default(0.00);
                $table->unsignedInteger('max_uses')->nullable();
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. Create product_reviews table
        if (!Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedTinyInteger('rating')->default(5);
                $table->text('comment')->nullable();
                $table->boolean('is_approved')->default(true);
                $table->timestamps();
            });
        }

        // 5. Create shipping_zones table
        if (!Schema::hasTable('shipping_zones')) {
            Schema::create('shipping_zones', function (Blueprint $table) {
                $table->id();
                $table->string('state');
                $table->string('city')->nullable();
                $table->decimal('fee', 12, 2)->default(1500.00);
                $table->string('estimated_days')->default('2-4 business days');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('coupons');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'discount_amount')) {
                    $table->dropColumn('discount_amount');
                }
                if (Schema::hasColumn('orders', 'coupon_code')) {
                    $table->dropColumn('coupon_code');
                }
            });
        }

        if (Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'is_default')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};

