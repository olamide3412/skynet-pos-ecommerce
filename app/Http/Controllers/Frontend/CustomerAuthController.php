<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\StoreSetting;
use App\Traits\MergesGuestCart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Rules\Turnstile;

class CustomerAuthController extends Controller
{
    use MergesGuestCart;

    public function showLogin()
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('home');
        }
        return Inertia::render('Frontend/Auth/Login');
    }

    public function showRegister()
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('home');
        }

        if (!StoreSetting::getBool('customer_registration_enabled', true)) {
            return redirect()->route('login')->with('warning', 'New customer registration is currently disabled.');
        }

        return Inertia::render('Frontend/Auth/Register');
    }

    public function register(Request $request)
    {
        if (!StoreSetting::getBool('customer_registration_enabled', true)) {
            return redirect()->route('login')->with('error', 'New customer registration is currently disabled.');
        }

        $turnstileEnabled = StoreSetting::getBool('turnstile_enabled', true);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:customers',
            'password' => 'required|string|confirmed|min:8',
            'phone' => 'nullable|string|max:20',
            'cf_turnstile_response' => $turnstileEnabled ? ['required', new Turnstile()] : ['nullable'],
        ]);

        $guestSessionId = $request->session()->getId();

        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        $this->mergeGuestCart($guestSessionId, $customer);

        try {
            \Illuminate\Support\Facades\Mail::to($customer->email)->queue(new \App\Mail\CustomerWelcomeMail($customer));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Customer #{$customer->id} welcome email failed to queue: " . $e->getMessage());
        }

        $companyName = StoreSetting::get('company_name', config('app.name', 'Skynet'));

        return redirect()->route('home')->with('success', "Registration successful! Welcome to {$companyName}.");
    }

    public function login(Request $request)
    {
        $turnstileEnabled = StoreSetting::getBool('turnstile_enabled', true);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'cf_turnstile_response' => $turnstileEnabled ? ['required', new Turnstile()] : ['nullable'],
        ]);
        
        unset($credentials['cf_turnstile_response']);

        $guestSessionId = $request->session()->getId();

        if (Auth::guard('customer')->attempt($credentials, $request->remember ?? false)) {
            $request->session()->regenerate();

            $customer = Auth::guard('customer')->user();
            $this->mergeGuestCart($guestSessionId, $customer);
            
            // If they are checking out, redirect them back to checkout
            if ($request->session()->pull('url.intended') == route('checkout.index')) {
                return redirect()->route('checkout.index');
            }

            return redirect()->intended(route('home'))->with('success', 'Logged in successfully.');
        }

        throw ValidationException::withMessages([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function destroy(Request $request)
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Logged out successfully.');
    }
}
