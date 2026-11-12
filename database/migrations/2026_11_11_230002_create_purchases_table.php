<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 100)->unique();
            $table->string('supplier_name');
            $table->string('invoice_reference', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('purchase_date');
            $table->decimal('total_cost', 12, 2)->default(0.00);
            $table->integer('total_items_count')->default(0);
            $table->string('payment_status', 30)->default('paid'); // paid, pending, partial
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_date');
            $table->index('supplier_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};

