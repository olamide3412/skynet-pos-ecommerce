<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Staff attribution
            $table->foreignId('created_by_staff_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->after('created_by_staff_id')->constrained('users')->nullOnDelete();
            $table->foreignId('remitted_by_staff_id')->nullable()->after('assigned_staff_id')->constrained('users')->nullOnDelete();
            $table->foreignId('reconciled_by_admin_id')->nullable()->after('remitted_by_staff_id')->constrained('users')->nullOnDelete();

            // Payment on Delivery (POD) cash lifecycle management
            // Options: 'none', 'awaiting_collection', 'cash_collected', 'remitted_to_store', 'reconciled'
            $table->string('pod_status')->default('none')->after('payment_method')->index();
            $table->decimal('pod_amount_collected', 12, 2)->nullable()->after('pod_status');
            $table->timestamp('pod_collected_at')->nullable()->after('pod_amount_collected');
            $table->timestamp('pod_remitted_at')->nullable()->after('pod_collected_at');
            $table->timestamp('pod_reconciled_at')->nullable()->after('pod_remitted_at');
            $table->text('pod_reconciliation_note')->nullable()->after('pod_reconciled_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['created_by_staff_id']);
            $table->dropForeign(['assigned_staff_id']);
            $table->dropForeign(['remitted_by_staff_id']);
            $table->dropForeign(['reconciled_by_admin_id']);

            $table->dropColumn([
                'created_by_staff_id',
                'assigned_staff_id',
                'remitted_by_staff_id',
                'reconciled_by_admin_id',
                'pod_status',
                'pod_amount_collected',
                'pod_collected_at',
                'pod_remitted_at',
                'pod_reconciled_at',
                'pod_reconciliation_note',
            ]);
        });
    }
};
