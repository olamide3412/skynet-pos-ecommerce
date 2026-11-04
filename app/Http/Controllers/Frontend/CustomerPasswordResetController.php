<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\CustomerResetPasswordMail;
use App\Models\Customer;
use App\Rules\Turnstile;
use Illuminate\Http\Request;
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
        return Inertia::render('Frontend/Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'cf_turnstile_response' => ['nullable', new Turnstile()],
        ]);

        $customer = Customer::where('email', $request->email)->first();

        // Prevent email enumeration: return generic success even if not found
        if ($customer) {
            $token = Password::broker('customers')->createToken($customer);

            try {
                Mail::to($customer->email)->send(new CustomerResetPasswordMail($customer, $token));
            } catch (\Throwable $e) {
                Log::error("Failed to send password reset email to {$customer->email}: " . $e->getMessage());
                return back()->with('error', 'Unable to deliver reset email. Please try again later.');
            }
        }

        return back()->with('status', 'If an account exists for this email address, a password reset link has been sent.');
    }

    public function showResetPassword(Request $request, string $token)
    {
        return Inertia::render('Frontend/Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email', ''),
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

