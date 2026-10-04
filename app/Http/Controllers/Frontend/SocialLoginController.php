<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class SocialLoginController extends Controller
{
    public function redirect($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            abort(404);
        }

        // Check if enabled in settings
        $settings = \App\Models\StoreSetting::pluck('value', 'key')->toArray();
        if (($settings["{$provider}_auth_enabled"] ?? '0') !== '1') {
            return redirect()->route('login')->with('error', ucfirst($provider) . ' login is currently disabled.');
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback($provider)
    {
        if (!in_array($provider, ['google', 'facebook'])) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
            
            // Check if customer exists by provider ID or Email
            $customer = Customer::where('provider_name', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if (!$customer) {
                // If not found by provider ID, check by email
                $customer = Customer::where('email', $socialUser->getEmail())->first();

                if ($customer) {
                    // Update existing customer with provider details
                    $customer->update([
                        'provider_name' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'provider_token' => $socialUser->token,
                    ]);
                } else {
                    // Create brand new customer
                    $customer = Customer::create([
                        'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                        'email' => $socialUser->getEmail(),
                        'provider_name' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'provider_token' => $socialUser->token,
                        // Null password because they use social login
                        'password' => null,
                        'email_verified_at' => now(), // Assume social emails are verified
                    ]);
                }
            } else {
                // Update token
                $customer->update([
                    'provider_token' => $socialUser->token,
                ]);
            }

            // Log the customer in using the customer guard
            Auth::guard('customer')->login($customer, true);

            return redirect()->route('home')->with('success', 'Successfully logged in with '.ucfirst($provider).'!');

        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Authentication failed: ' . $e->getMessage());
        }
    }
}
