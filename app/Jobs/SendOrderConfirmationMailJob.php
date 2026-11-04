<?php

namespace App\Jobs;

use App\Mail\AdminNewOrderAlertMail;
use App\Mail\CustomerOrderConfirmationMail;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order;
    public int $tries = 3;
    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->order->loadMissing(['items.product', 'customer']);

        // 1. Send confirmation to customer / buyer
        $recipientEmail = $this->order->billing_email ?? $this->order->customer?->email ?? $this->order->guest_email;

        if (!empty($recipientEmail)) {
            try {
                Mail::to($recipientEmail)->send(new CustomerOrderConfirmationMail($this->order));
                Log::info("Order confirmation email sent to {$recipientEmail} for Order #{$this->order->id} (Ref: {$this->order->tracking_number}).");
            } catch (\Throwable $e) {
                Log::error("Failed to send customer order confirmation email for Order #{$this->order->id}: " . $e->getMessage());
                throw $e; // Re-throw so Laravel queue will retry according to $tries
            }
        }

        // 2. Send new order alert to store admin
        $adminEmail = StoreSetting::get('company_email')
            ?: StoreSetting::get('admin_email')
            ?: config('mail.from.address');

        if (!empty($adminEmail)) {
            try {
                Mail::to($adminEmail)->send(new AdminNewOrderAlertMail($this->order));
                Log::info("Admin new order alert email sent to {$adminEmail} for Order #{$this->order->id}.");
            } catch (\Throwable $e) {
                Log::warning("Failed to send admin order alert email for Order #{$this->order->id}: " . $e->getMessage());
                // Do not re-throw if only admin email fails and customer already received theirs
            }
        }
    }
}

