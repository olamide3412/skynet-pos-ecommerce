<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\InventoryService;
use App\Services\PosIntegration\PosReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireAbandonedOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:expire-abandoned';

    /**
     * The console command description.
     */
    protected $description = 'Expire pending abandoned orders, release POS reservations, and restore local e-commerce stock';

    /**
     * Execute the console command.
     */
    public function handle(
        PosReservationService $reservationService,
        InventoryService $inventoryService
    ): int {
        $threshold = now()->subMinutes(35);

        // Find orders that are still pending beyond the 30-35 min window or where reservation expired
        $abandonedOrders = Order::where('status', 'pending')
            ->where(function ($query) use ($threshold) {
                $query->where('created_at', '<', $threshold)
                    ->orWhere(function ($q) {
                        $q->whereNotNull('pos_reservation_expires_at')
                            ->where('pos_reservation_expires_at', '<', now());
                    });
            })
            ->get();

        if ($abandonedOrders->isEmpty()) {
            $this->info('No abandoned pending orders found to expire.');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($abandonedOrders as $order) {
            // 1. Release POS reservation if active
            if ($order->pos_reservation_status === 'reserved') {
                $reservationService->releaseReservation($order);
            }

            // 2. Restore local e-commerce inventory
            $inventoryService->restoreOrderInventory($order);

            // 3. Mark pending payments as cancelled/expired
            Payment::where('order_id', $order->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);

            // 4. Update order status
            $order->update([
                'status'                 => 'cancelled',
                'pos_reservation_status' => 'expired',
            ]);

            // 5. Reverse any pending affiliate commission
            app(\App\Services\Referral\AffiliateCommissionService::class)->handleOrderCancelled($order, 'Order checkout abandoned / expired');

            Log::info("Expired abandoned checkout for Order #{$order->id} (Ref: {$order->payment_reference}). Inventory restored.");
            $count++;
        }

        ActivityLogger::system("Abandoned orders sweeper expired {$count} unpaid order(s) and restored stock", [
            'expired_count' => $count,
        ]);

        $this->info("Successfully expired {$count} abandoned order(s) and restored stock.");
        return Command::SUCCESS;
    }
}

