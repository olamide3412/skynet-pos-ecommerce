<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PosIntegration\PosReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryPosReservationConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order;
    public int $tries = 5;
    public int $backoff = 60;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function handle(PosReservationService $reservationService): void
    {
        $success = $reservationService->confirmReservation($this->order, true);
        if (!$success) {
            $this->release(60);
        }
    }
}
