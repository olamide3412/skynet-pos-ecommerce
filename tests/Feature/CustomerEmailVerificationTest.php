<?php

namespace Tests\Feature;

use App\Mail\CustomerVerifyEmailMail;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        StoreSetting::set('turnstile_enabled', '0');
        StoreSetting::set('customer_registration_enabled', '1');
    }

    public function test_registration_does_not_log_in_customer_sends_verification_mail_and_preserves_cart(): void
    {
        Mail::fake();

        // 1. Set up a category and product
        $category = Category::create(['name' => 'Accessories', 'slug' => 'accessories']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Wireless Earbuds',
            'slug' => 'wireless-earbuds',
            'url_key' => 'wireless-earbuds',
            'price' => 15000,
            'stock' => 10,
            'status' => true,
        ]);

        // 2. Customer registers
        $response = $this->post(route('customer.register.submit'), [
            'name'                  => 'Amaka Obi',
            'email'                 => 'amaka@example.com',
            'password'              => 'Secret1234!',
            'password_confirmation' => 'Secret1234!',
            'phone'                 => '+2348011223344',
        ]);

        // 3. Verification expectations
        $response->assertRedirect(route('verification.notice'));
        $this->assertFalse(Auth::guard('customer')->check(), 'Customer must NOT be signed in yet!');

        $customer = Customer::where('email', 'amaka@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertNull($customer->email_verified_at);

        // Verification email dispatched
        Mail::assertSent(CustomerVerifyEmailMail::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });

        // 4. Test Cart Preservation (MergesGuestCart trait)
        $guestSessionId = 'guest_sess_amaka_123';
        $guestCart = Cart::create(['session_id' => $guestSessionId]);
        $guestCart->items()->create([
            'product_id' => $product->id,
            'quantity'   => 2,
            'price'      => 15000,
            'unit_name'  => 'Piece',
        ]);

        $tester = new class {
            use \App\Traits\MergesGuestCart;
            public function testSync($sessionId, $cust) {
                $this->syncGuestCartToUnverifiedCustomer($sessionId, $cust);
            }
            public function testMerge($sessionId, $cust) {
                $this->mergeGuestCart($sessionId, $cust);
            }
        };

        // Snapshot guest cart to unverified customer: guest cart preserved AND customer has items
        $tester->testSync($guestSessionId, $customer);

        $this->assertDatabaseHas('carts', ['session_id' => $guestSessionId]);
        $customerCart = Cart::where('customer_id', $customer->id)->first();
        $this->assertNotNull($customerCart);
        $this->assertEquals(1, $customerCart->items()->count());
        $this->assertEquals(2, $customerCart->items()->first()->quantity);

        // Upon verified login: guest cart is cleaned up and customer keeps items
        $tester->testMerge($guestSessionId, $customer);
        $this->assertDatabaseMissing('carts', ['session_id' => $guestSessionId]);
        $this->assertEquals(2, $customerCart->fresh()->items()->first()->quantity);
    }

    public function test_unverified_customer_cannot_login_and_is_redirected_to_verify_email(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => null,
        ]);

        $response = $this->post(route('customer.login.submit'), [
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertFalse(Auth::guard('customer')->check(), 'Unverified customer must not be logged in!');

        Mail::assertSent(CustomerVerifyEmailMail::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });
    }

    public function test_resend_verification_email_enforces_30_second_cooldown(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'name' => 'Chidi Eze',
            'email' => 'chidi@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => null,
        ]);

        // 1. First resend: succeeds
        $res1 = $this->post(route('verification.resend'), ['email' => $customer->email]);
        $res1->assertSessionHas('success');
        Mail::assertSent(CustomerVerifyEmailMail::class, 1);

        // 2. Immediate resend: blocked by 30s cooldown
        $res2 = $this->post(route('verification.resend'), ['email' => $customer->email]);
        $res2->assertSessionHas('warning');
        Mail::assertSent(CustomerVerifyEmailMail::class, 1);

        // 3. Advance clock by 31 seconds
        $this->travel(31)->seconds();

        // 4. Second resend: succeeds
        $res3 = $this->post(route('verification.resend'), ['email' => $customer->email]);
        $res3->assertSessionHas('success');
        Mail::assertSent(CustomerVerifyEmailMail::class, 2);
    }

    public function test_clicking_signed_verification_url_verifies_email_and_enables_login(): void
    {
        $customer = Customer::create([
            'name' => 'Kemi Adebayo',
            'email' => 'kemi@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => null,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $customer->id,
                'hash' => sha1($customer->getEmailForVerification()),
            ]
        );

        $response = $this->get($signedUrl);
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Frontend/Auth/VerifySuccess')
            ->where('customer.email', 'kemi@example.com')
            ->where('alreadyVerified', false)
        );

        $customer->refresh();
        $this->assertNotNull($customer->email_verified_at);
        $this->assertTrue($customer->hasVerifiedEmail());

        // Now login succeeds
        $loginRes = $this->post(route('customer.login.submit'), [
            'email' => 'kemi@example.com',
            'password' => 'Password123!',
        ]);

        $loginRes->assertRedirect(route('home'));
        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertEquals($customer->id, Auth::guard('customer')->id());
    }

    public function test_customer_profile_update_ignores_email_change_leaving_it_readonly(): void
    {
        $customer = Customer::create([
            'name' => 'Verified User',
            'email' => 'verified@example.com',
            'password' => Hash::make('Password123!'),
            'phone' => '+2348000000000',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')->put(route('customer.profile.update'), [
            'name' => 'Updated Name',
            'email' => 'attempted_new_email@example.com',
            'phone' => '+2348111111111',
        ]);

        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertEquals('Updated Name', $customer->name);
        $this->assertEquals('+2348111111111', $customer->phone);
        $this->assertEquals('verified@example.com', $customer->email, 'Customer email must remain unchanged and read-only!');
    }
}
