<?php

namespace App\Jobs;

use App\Mail\AdminNewOrderAlertMail;
use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAdminNewOrderAlertJob implements ShouldQueue
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
        $notificationsEnabled = StoreSetting::get('order_notifications_enabled') !== '0';

        if (!$notificationsEnabled) {
            return;
        }

        $this->order->loadMissing(['items.product']);

        $rawRecipients = StoreSetting::get('order_notification_emails');
        $staffEmails = [];

        if (!empty($rawRecipients)) {
            // Split by comma, semicolon, or newline
            $parts = preg_split('/[\r\n,;]+/', $rawRecipients);
            foreach ($parts as $part) {
                $cleaned = trim($part);
                if (!empty($cleaned) && filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                    $staffEmails[] = strtolower($cleaned);
                }
            }
            $staffEmails = array_values(array_unique($staffEmails));
        }

        // Fallback to store company email if no specific staff emails entered
        if (empty($staffEmails)) {
            $fallback = StoreSetting::get('company_email')
                ?: StoreSetting::get('admin_email')
                ?: config('mail.from.address');

            if (!empty($fallback) && filter_var($fallback, FILTER_VALIDATE_EMAIL)) {
                $staffEmails = [strtolower(trim($fallback))];
            }
        }

        if (empty($staffEmails)) {
            Log::warning("No staff or company email configured for order notification on Order #{$this->order->id}.");
            return;
        }

        try {
            Mail::to($staffEmails)->send(new AdminNewOrderAlertMail($this->order));
            $recipientsList = implode(', ', $staffEmails);
            Log::info("Admin new order alert email sent to [{$recipientsList}] for Order #{$this->order->id}.");
        } catch (\Throwable $e) {
            Log::error("Failed to send admin order alert email for Order #{$this->order->id}: " . $e->getMessage());
            throw $e;
        }
    }
}

