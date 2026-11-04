<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public Customer $customer;
    public string $storeName;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to {$this->storeName}! 🎉",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }
}

