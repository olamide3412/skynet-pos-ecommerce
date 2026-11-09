<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class CustomerSocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_social_redirect_aborts_for_unsupported_provider(): void
    {
        $response = $this->get('/auth/unsupported/redirect');
        $response->assertStatus(404);
    }

    public function test_social_redirect_fails_if_provider_is_disabled_in_settings(): void
    {
        StoreSetting::set('google_auth_enabled', '0');

        $response = $this->get(route('social.redirect', 'google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Google login is currently disabled by the store.');
    }

    public function test_social_redirect_warns_if_credentials_are_missing(): void
    {
        StoreSetting::set('google_auth_enabled', '1');
        StoreSetting::set('google_client_id', '');
        StoreSetting::set('google_client_secret', '');
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

        $response = $this->get(route('social.redirect', 'google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_social_redirect_initiates_oauth_when_enabled_and_configured(): void
    {
        StoreSetting::set('google_auth_enabled', '1');
        StoreSetting::set('google_client_id', 'test-client-id');
        StoreSetting::set('google_client_secret', 'test-client-secret');

        $mockProvider = Mockery::mock(SocialiteProvider::class);
        $mockProvider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.google.com/o/oauth2/auth?test=1'));

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($mockProvider);

        $response = $this->get(route('social.redirect', 'google'));
        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth?test=1');
    }

    public function test_social_callback_handles_user_cancel_or_error_query_param(): void
    {
        StoreSetting::set('google_auth_enabled', '1');
        StoreSetting::set('google_client_id', 'test-client-id');
        StoreSetting::set('google_client_secret', 'test-client-secret');

        $response = $this->get(route('social.callback', ['provider' => 'google', 'error' => 'access_denied', 'error_description' => 'The user denied the request']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('warning');
    }

    public function test_social_callback_creates_new_customer_and_logs_in(): void
    {
        StoreSetting::set('google_auth_enabled', '1');
        StoreSetting::set('google_client_id', 'test-client-id');
        StoreSetting::set('google_client_secret', 'test-client-secret');

        $socialUser = new SocialiteUser();
        $socialUser->id = 'google-12345678';
        $socialUser->name = 'Jane Google';
        $socialUser->email = 'jane.google@example.com';
        $socialUser->token = 'mock-google-token-xyz';

        $mockDriver = Mockery::mock(SocialiteProvider::class);
        $mockDriver->shouldReceive('user')
            ->once()
            ->andReturn($socialUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->once()
            ->andReturn($mockDriver);

        $response = $this->get(route('social.callback', 'google'));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('customers', [
            'email' => 'jane.google@example.com',
            'name' => 'Jane Google',
            'provider_name' => 'google',
            'provider_id' => 'google-12345678',
        ]);

        $customer = Customer::where('email', 'jane.google@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNull($customer->password);
        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertEquals($customer->id, Auth::guard('customer')->id());
    }

    public function test_social_callback_links_existing_customer_by_email(): void
    {
        StoreSetting::set('facebook_auth_enabled', '1');
        StoreSetting::set('facebook_client_id', 'fb-app-123');
        StoreSetting::set('facebook_client_secret', 'fb-secret-456');

        $existingCustomer = Customer::create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'password' => bcrypt('password123'),
        ]);

        $socialUser = new SocialiteUser();
        $socialUser->id = 'fb-999888';
        $socialUser->name = 'Existing Customer FB';
        $socialUser->email = 'existing@example.com';
        $socialUser->token = 'mock-fb-token';

        $mockDriver = Mockery::mock(SocialiteProvider::class);
        $mockDriver->shouldReceive('user')
            ->once()
            ->andReturn($socialUser);

        Socialite::shouldReceive('driver')
            ->with('facebook')
            ->once()
            ->andReturn($mockDriver);

        $response = $this->get(route('social.callback', 'facebook'));

        $response->assertRedirect(route('home'));
        $this->assertTrue(Auth::guard('customer')->check());
        $this->assertEquals($existingCustomer->id, Auth::guard('customer')->id());

        $existingCustomer->refresh();
        $this->assertEquals('facebook', $existingCustomer->provider_name);
        $this->assertEquals('fb-999888', $existingCustomer->provider_id);
    }

    public function test_merge_guest_cart_transfers_items_to_authenticated_customer(): void
    {
        $category = \App\Models\Category::create(['name' => 'Tech', 'slug' => 'tech']);
        $product = Product::create([
            'name'                 => 'Test Social Phone',
            'slug'                 => 'test-social-phone',
            'url_key'              => 'test-social-phone',
            'category_id'          => $category->id,
            'price'                => 150000,
            'stock'                => 10,
            'status'               => true,
            'inventory_source'     => 'ecommerce',
            'visible_individually' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Cart Tester',
            'email' => 'carttester@example.com',
            'password' => null,
        ]);

        $guestSessionId = 'guest_sess_abc123';
        $guestCart = Cart::create(['session_id' => $guestSessionId]);
        CartItem::create([
            'cart_id' => $guestCart->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 150000,
        ]);

        $controller = new class {
            use \App\Traits\MergesGuestCart;
            public function testMerge($sessionId, $customer) {
                $this->mergeGuestCart($sessionId, $customer);
            }
        };

        $controller->testMerge($guestSessionId, $customer);

        $customerCart = Cart::where('customer_id', $customer->id)->first();
        $this->assertNotNull($customerCart);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $customerCart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('carts', ['session_id' => $guestSessionId]);
    }
}
