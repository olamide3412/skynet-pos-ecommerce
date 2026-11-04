<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public Customer $customer;
    public string $token;
    public string $resetUrl;
    public string $storeName;

    public function __construct(Customer $customer, string $token)
    {
        $this->customer = $customer;
        $this->token = $token;
        $this->resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $customer->email,
        ]);
        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reset Your {$this->storeName} Password",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-reset-password',
        );
    }
}

