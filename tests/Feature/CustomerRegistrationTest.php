<?php

namespace Tests\Feature;

use App\Mail\CustomerWelcomeMail;
use App\Models\Customer;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_queues_welcome_email_and_redirects_quickly(): void
    {
        Mail::fake();

        // Fake Cloudflare Turnstile verification response
        Http::fake([
            'https://challenges.cloudflare.com/*' => Http::response([
                'success' => true,
            ], 200),
        ]);

        StoreSetting::set('company_name', 'Apex Digital Hub');

        $response = $this->post(route('customer.register.submit'), [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.com',
            'password'              => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'phone'                 => '+2348012345678',
            'cf_turnstile_response' => 'test-valid-turnstile-token',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success', 'Registration successful! Welcome to Apex Digital Hub.');

        $this->assertDatabaseHas('customers', [
            'name'  => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        // Verify the welcome email was queued (NOT sent synchronously during HTTP request)
        Mail::assertQueued(CustomerWelcomeMail::class, function ($mail) {
            return $mail->hasTo('jane@example.com');
        });
    }
}

