<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\StoreSetting;
use App\Services\ActivityLogger;
use App\Traits\MergesGuestCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialLoginController extends Controller
{
    use MergesGuestCart;

    /**
     * Redirect the user to the provider's OAuth authorization page.
     */
    public function redirect(Request $request, string $provider)
    {
        if (!in_array($provider, ['google', 'facebook'], true)) {
            abort(404);
        }

        // 1. Verify provider is enabled in store settings
        if (!StoreSetting::getBool("{$provider}_auth_enabled", false)) {
            return redirect()->route('login')->with('error', ucfirst($provider) . ' login is currently disabled by the store.');
        }

        // 2. Resolve credentials dynamically (StoreSetting takes priority over .env)
        $clientId = StoreSetting::get("{$provider}_client_id") ?: config("services.{$provider}.client_id");
        $clientSecret = StoreSetting::get("{$provider}_client_secret") ?: config("services.{$provider}.client_secret");

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('login')->with(
                'error',
                ucfirst($provider) . ' login is not fully configured yet. Please configure the Client ID & Secret in Store Settings.'
            );
        }

        // Ensure current request config is bound
        config([
            "services.{$provider}.client_id" => $clientId,
            "services.{$provider}.client_secret" => $clientSecret,
        ]);

        try {
            return Socialite::driver($provider)->redirect();
        } catch (Throwable $e) {
            Log::error("Socialite redirect error [{$provider}]: " . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('login')->with(
                'error',
                'Unable to initiate ' . ucfirst($provider) . ' login: ' . $e->getMessage()
            );
        }
    }

    /**
     * Handle the incoming OAuth callback from the provider.
     */
    public function callback(Request $request, string $provider)
    {
        if (!in_array($provider, ['google', 'facebook'], true)) {
            abort(404);
        }

        // Check if enabled
        if (!StoreSetting::getBool("{$provider}_auth_enabled", false)) {
            return redirect()->route('login')->with('error', ucfirst($provider) . ' login is currently disabled.');
        }

        // User canceled or provider returned error
        if ($request->has('error')) {
            $errorDesc = $request->get('error_description') ?: $request->get('error');
            return redirect()->route('login')->with(
                'warning',
                ucfirst($provider) . ' login was canceled or denied: ' . $errorDesc
            );
        }

        // Resolve credentials
        $clientId = StoreSetting::get("{$provider}_client_id") ?: config("services.{$provider}.client_id");
        $clientSecret = StoreSetting::get("{$provider}_client_secret") ?: config("services.{$provider}.client_secret");

        config([
            "services.{$provider}.client_id" => $clientId,
            "services.{$provider}.client_secret" => $clientSecret,
        ]);

        try {
            $socialUser = Socialite::driver($provider)->user();

            $providerId = (string) $socialUser->getId();
            $providerToken = $socialUser->token ?? null;
            $email = $socialUser->getEmail();
            $name = $socialUser->getName() ?: ($socialUser->getNickname() ?: ucfirst($provider) . ' User');

            // Fallback email for providers that do not provide an email (e.g., phone-only Facebook accounts)
            if (empty($email)) {
                $email = "{$provider}_{$providerId}@auth.local";
            }

            // 1. Locate customer by provider_id & provider_name
            $customer = Customer::where('provider_name', $provider)
                ->where('provider_id', $providerId)
                ->first();

            if (!$customer) {
                // 2. Locate by email
                $customer = Customer::where('email', $email)->first();

                if ($customer) {
                    // Link existing customer
                    $customer->update([
                        'provider_name'  => $provider,
                        'provider_id'    => $providerId,
                        'provider_token' => $providerToken,
                        'email_verified_at' => $customer->email_verified_at ?? now(),
                    ]);
                } else {
                    // Create brand new customer
                    $customer = Customer::create([
                        'name'              => $name,
                        'email'             => $email,
                        'provider_name'     => $provider,
                        'provider_id'       => $providerId,
                        'provider_token'    => $providerToken,
                        'password'          => null,
                        'email_verified_at' => now(),
                    ]);
                }
            } else {
                // Refresh provider token and name if updated
                $customer->update([
                    'provider_token' => $providerToken,
                    'name'           => $customer->name ?: $name,
                ]);
            }

            $guestSessionId = $request->session()->getId();

            // Log customer in using 'customer' guard
            Auth::guard('customer')->login($customer, true);
            $request->session()->regenerate();

            // Merge any guest cart items into customer's cart
            $this->mergeGuestCart($guestSessionId, $customer);

            // Log login event
            ActivityLogger::customer(
                "Customer logged in via " . ucfirst($provider) . " ({$customer->email})",
                [
                    'customer_id' => $customer->id,
                    'provider'    => $provider,
                    'email'       => $customer->email,
                ],
                null,
                'customer'
            );

            return redirect()->intended(route('home'))->with(
                'success',
                'Successfully signed in with ' . ucfirst($provider) . '! Welcome, ' . $customer->name . '.'
            );

        } catch (Throwable $e) {
            Log::error("Socialite callback error [{$provider}]: " . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('login')->with(
                'error',
                'Authentication failed with ' . ucfirst($provider) . ': ' . $e->getMessage()
            );
        }
    }
}

