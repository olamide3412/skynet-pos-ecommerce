<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\PosIntegration\PosReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryPendingPosConfirmationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:retry-pos-confirmations';

    /**
     * The console command description.
     */
    protected $description = 'Retry confirming POS reservations for paid/confirmed orders that experienced sync errors';

    /**
     * Execute the console command.
     */
    public function handle(PosReservationService $reservationService): int
    {
        $this->info('Checking for paid orders with pending POS confirmations...');

        // Find orders that are confirmed/paid but POS reservation is still not confirmed
        $pendingOrders = Order::where(function ($query) {
                $query->whereIn('status', ['paid', 'processing', 'completed'])
                      ->orWhere('payment_method', 'pod');
            })
            ->where('pos_reservation_status', '!=', 'confirmed')
            ->whereNotNull('inventory_reference')
            ->where(function ($query) {
                $query->whereNotNull('pos_sync_error')
                      ->orWhere('pos_reservation_status', 'reserved');
            })
            ->get();

        if ($pendingOrders->isEmpty()) {
            $this->info('No pending POS reservation confirmations found.');
            return Command::SUCCESS;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($pendingOrders as $order) {
            $this->line("Retrying confirmation for Order #{$order->id} (Ref: {$order->inventory_reference})...");

            $success = $reservationService->confirmReservation($order, true);

            if ($success) {
                $this->info("✓ Order #{$order->id} POS reservation confirmed successfully.");
                $successCount++;
            } else {
                $this->warn("✗ Order #{$order->id} confirmation failed: {$order->pos_sync_error}");
                $failCount++;
            }
        }

        $this->info("Completed POS confirmations retry sweep. Succeeded: {$successCount}, Failed: {$failCount}.");
        return Command::SUCCESS;
    }
}

