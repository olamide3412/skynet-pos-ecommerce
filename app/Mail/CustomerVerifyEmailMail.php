<?php

namespace App\Mail;

use App\Models\Customer;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class CustomerVerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public Customer $customer;
    public string $verificationUrl;
    public string $storeName;
    public string $themeColor;
    public ?string $companyLogo;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;

        // Generate a 60-minute signed verification URL
        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $customer->getKey(),
                'hash' => sha1($customer->getEmailForVerification()),
            ]
        );

        // If StoreSetting has an explicit baseUrl (e.g. production domain), replace host
        $baseUrl = StoreSetting::getBaseUrl();
        if ($baseUrl && filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            $parsedBase = parse_url($baseUrl);
            $parsedSigned = parse_url($signedUrl);

            $scheme = $parsedBase['scheme'] ?? ($parsedSigned['scheme'] ?? 'http');
            $host = $parsedBase['host'] ?? ($parsedSigned['host'] ?? 'localhost');
            $port = isset($parsedBase['port']) ? ':' . $parsedBase['port'] : '';
            $path = $parsedSigned['path'] ?? '/';
            $query = isset($parsedSigned['query']) ? '?' . $parsedSigned['query'] : '';

            $this->verificationUrl = "{$scheme}://{$host}{$port}{$path}{$query}";
        } else {
            $this->verificationUrl = $signedUrl;
        }

        $this->storeName = StoreSetting::get('company_name', config('app.name', 'Skynet Store'));
        $this->themeColor = StoreSetting::get('theme_primary_color', '#FF4B00');
        $this->companyLogo = StoreSetting::get('company_logo', null);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Verify Your Email Address - {$this->storeName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-verify-email',
        );
    }
}

