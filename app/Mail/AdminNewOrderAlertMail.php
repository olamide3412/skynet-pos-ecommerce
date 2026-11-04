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

class AdminNewOrderAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;
    public string $storeName;
    public string $companyEmail;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing('items.product');
        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
        $this->companyEmail = StoreSetting::get('company_email', config('mail.from.address'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order #{$this->order->tracking_number} received [₦" . number_format($this->order->total_amount, 2) . "]",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-new-order',
        );
    }
}

