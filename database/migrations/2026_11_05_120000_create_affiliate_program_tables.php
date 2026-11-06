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
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
            $table->string('referral_code', 32)->unique();
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected, suspended
            $table->string('commission_type', 20)->nullable(); // percentage, flat (null = use store default)
            $table->decimal('commission_rate', 10, 2)->nullable(); // null = use store default
            $table->string('bank_name')->nullable();
            $table->string('account_number', 30)->nullable();
            $table->string('account_name')->nullable();
            $table->decimal('wallet_balance', 12, 2)->default(0.00);
            $table->decimal('total_earned', 12, 2)->default(0.00);
            $table->decimal('total_withdrawn', 12, 2)->default(0.00);
            $table->text('application_note')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('affiliate_referral_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('landing_url')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete();
            $table->decimal('order_amount_base', 12, 2);
            $table->string('commission_type', 20); // percentage, flat
            $table->decimal('commission_rate', 10, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->string('status', 20)->default('pending')->index(); // pending, available, reversed, paid
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversed_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('source_type')->nullable(); // commission, withdrawal_hold, withdrawal_refund, manual_adjustment
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('affiliate_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('status', 20)->default('pending')->index(); // pending, paid, rejected
            $table->string('bank_name');
            $table->string('account_number', 30);
            $table->string('account_name');
            $table->string('payment_reference')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliate_withdrawals');
        Schema::dropIfExists('affiliate_wallet_transactions');
        Schema::dropIfExists('affiliate_commissions');
        Schema::dropIfExists('affiliate_referral_visits');
        Schema::dropIfExists('affiliates');
    }
};

