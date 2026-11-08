<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\CustomerResetPasswordMail;
use App\Models\Customer;
use App\Models\StoreSetting;
use App\Rules\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerPasswordResetController extends Controller
{
    public function showForgotPassword()
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('home');
        }

        return Inertia::render('Frontend/Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request)
    {
        $turnstileEnabled = StoreSetting::getBool('turnstile_enabled', true);

        $request->validate([
            'email' => ['required', 'email'],
            'cf_turnstile_response' => $turnstileEnabled ? ['required', new Turnstile()] : ['nullable'],
        ]);

        $customer = Customer::where('email', $request->email)->first();

        // Prevent email enumeration: return generic success even if not found
        if ($customer) {
            $broker = Password::broker('customers');

            // Respect cooldown window to prevent email flooding
            if ($broker->getRepository()->recentlyCreatedToken($customer)) {
                return back()->with('warning', 'Please wait a moment before requesting another password reset link.');
            }

            $token = $broker->createToken($customer);

            try {
                Mail::to($customer->email)->send(new CustomerResetPasswordMail($customer, $token));
            } catch (\Throwable $e) {
                Log::error("Failed to send password reset email to {$customer->email}: " . $e->getMessage());
                return back()->with('error', 'Unable to deliver reset email. Please try again later.');
            }
        }

        $message = 'If an account exists for this email address, a password reset link has been sent.';

        return back()
            ->with('status', $message)
            ->with('success', $message);
    }

    public function showResetPassword(Request $request, string $token)
    {
        if (Auth::guard('customer')->check()) {
            return redirect()->route('home');
        }

        $email = (string) $request->query('email', '');
        if ($email !== '') {
            $customer = Customer::where('email', $email)->first();
            if (!$customer || !Password::broker('customers')->tokenExists($customer, $token)) {
                return redirect()->route('password.request')
                    ->with('error', 'This password reset link is invalid or has expired. Please request a new one.');
            }
        }

        return Inertia::render('Frontend/Auth/ResetPassword', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|confirmed|min:8',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (!$customer || !Password::broker('customers')->tokenExists($customer, $request->token)) {
            throw ValidationException::withMessages([
                'email' => ['This password reset link is invalid or has expired.'],
            ]);
        }

        $customer->update([
            'password'       => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ]);

        Password::broker('customers')->deleteToken($customer);

        return redirect()->route('login')->with('success', 'Your password has been reset successfully. Please log in with your new password.');
    }
}

