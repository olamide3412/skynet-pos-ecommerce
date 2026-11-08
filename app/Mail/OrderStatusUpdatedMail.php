<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;
    public string $status;
    public string $storeName;
    public string $companyEmail;
    public ?string $companyLogo;
    public string $themeColor;

    public function __construct(Order $order, string $status)
    {
        $this->order = $order->loadMissing('items.product');
        $this->status = $status;
        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
        $this->companyEmail = StoreSetting::get('company_email', config('mail.from.address', 'support@skynetdigitalltd.com.ng'));
        $this->companyLogo = StoreSetting::get('company_logo', null);
        $this->themeColor = StoreSetting::get('theme_primary_color', '#FF4B00');
    }

    public function envelope(): Envelope
    {
        $statusLabel = ucfirst($this->status);
        return new Envelope(
            subject: "Update on Order #{$this->order->tracking_number}: {$statusLabel}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-status-updated',
        );
    }
}

