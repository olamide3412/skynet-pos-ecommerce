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

class CustomerOrderConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Order $order;
    public string $storeName;
    public string $companyEmail;
    public string $companyPhone;
    public string $companyAddress;
    public ?string $companyLogo;

    public function __construct(Order $order)
    {
        $this->order = $order->loadMissing('items.product');
        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
        $this->companyEmail = StoreSetting::get('company_email', 'support@skynetdigitalhub.com.ng');
        $this->companyPhone = StoreSetting::get('company_phone', '+234 803 207 2831');
        $this->companyAddress = StoreSetting::get('company_address', 'Delta State, Nigeria');
        $this->companyLogo = StoreSetting::get('company_logo', null);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Order Confirmation #{$this->order->tracking_number} - {$this->storeName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
        );
    }
}

