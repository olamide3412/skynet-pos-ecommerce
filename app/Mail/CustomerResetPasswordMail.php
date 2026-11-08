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
    public string $themeColor;
    public ?string $companyLogo;

    public function __construct(Customer $customer, string $token)
    {
        $this->customer = $customer;
        $this->token = $token;
        $relativeReset = route('password.reset', [
            'token' => $token,
            'email' => $customer->email,
        ], false);
        $this->resetUrl = StoreSetting::getBaseUrl(ltrim($relativeReset, '/'));
        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
        $this->themeColor = StoreSetting::get('theme_primary_color', '#FF4B00');
        $this->companyLogo = StoreSetting::get('company_logo', null);
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

