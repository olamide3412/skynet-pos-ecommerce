<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Customer $customer;
    public string $storeName;
    public string $themeColor;
    public ?string $companyLogo;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
        $this->themeColor = StoreSetting::get('theme_primary_color', '#FF4B00');
        $this->companyLogo = StoreSetting::get('company_logo', null);
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

