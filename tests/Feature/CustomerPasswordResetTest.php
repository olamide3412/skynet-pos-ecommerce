<?php

namespace Tests\Feature;

use App\Mail\CustomerResetPasswordMail;
use App\Models\Customer;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_password_reset_when_turnstile_disabled_in_store_settings(): void
    {
        Mail::fake();
        StoreSetting::set('turnstile_enabled', '0');

        $customer = Customer::create([
            'name'     => 'Jane Doe',
            'email'    => 'jane@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        // No cf_turnstile_response sent, should succeed because turnstile_enabled is 0
        $response = $this->post(route('password.email'), [
            'email' => 'jane@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $response->assertSessionHas('success');

        Mail::assertSent(CustomerResetPasswordMail::class, function (CustomerResetPasswordMail $mail) use ($customer) {
            return $mail->hasTo($customer->email) && !empty($mail->token);
        });
    }

    public function test_turnstile_is_enforced_on_forgot_password_when_enabled(): void
    {
        Mail::fake();
        StoreSetting::set('turnstile_enabled', '1');

        Customer::create([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        // Missing cf_turnstile_response fails validation when turnstile_enabled is 1
        $response = $this->post(route('password.email'), [
            'email' => 'john@example.com',
        ]);

        $response->assertSessionHasErrors('cf_turnstile_response');
        Mail::assertNothingSent();

        // With valid mocked Turnstile verification, it succeeds
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true], 200),
        ]);

        $validResponse = $this->post(route('password.email'), [
            'email'                 => 'john@example.com',
            'cf_turnstile_response' => 'valid-mock-token',
        ]);

        $validResponse->assertSessionHasNoErrors();
        Mail::assertSent(CustomerResetPasswordMail::class);
    }

    public function test_customer_can_reset_password_with_valid_token_and_invalid_token_is_rejected(): void
    {
        StoreSetting::set('turnstile_enabled', '0');

        $customer = Customer::create([
            'name'     => 'Sarah Connor',
            'email'    => 'sarah@example.com',
            'password' => Hash::make('oldpassword123'),
        ]);

        $token = Password::broker('customers')->createToken($customer);

        // Visiting reset page with invalid token redirects back to forgot-password
        $badVisit = $this->get(route('password.reset', ['token' => 'invalid-token', 'email' => 'sarah@example.com']));
        $badVisit->assertRedirect(route('password.request'));

        // Visiting reset page with valid token succeeds
        $goodVisit = $this->get(route('password.reset', ['token' => $token, 'email' => 'sarah@example.com']));
        $goodVisit->assertStatus(200);

        // Reset password
        $resetResponse = $this->post(route('password.update'), [
            'token'                 => $token,
            'email'                 => 'sarah@example.com',
            'password'              => 'newstrongpassword123',
            'password_confirmation' => 'newstrongpassword123',
        ]);

        $resetResponse->assertRedirect(route('login'));
        $resetResponse->assertSessionHas('success');

        $this->assertTrue(Hash::check('newstrongpassword123', $customer->fresh()->password));
        $this->assertFalse(Password::broker('customers')->tokenExists($customer->fresh(), $token));
    }
}

