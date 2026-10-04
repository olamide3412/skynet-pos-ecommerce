<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Rules\Turnstile;

class CustomerAuthController extends Controller
{
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
        return Inertia::render('Frontend/Auth/Register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:customers',
            'password' => 'required|string|confirmed|min:8',
            'phone' => 'nullable|string|max:20',
            'cf_turnstile_response' => ['required', new Turnstile()],
        ]);

        $customer = Customer::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
        ]);

        Auth::guard('customer')->login($customer);

        return redirect()->route('home')->with('success', 'Registration successful! Welcome to Skynet Digital Store.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'cf_turnstile_response' => ['required', new Turnstile()],
        ]);
        
        unset($credentials['cf_turnstile_response']);

        if (Auth::guard('customer')->attempt($credentials, $request->remember ?? false)) {
            $request->session()->regenerate();
            
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
