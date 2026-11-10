<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\CustomerVerifyEmailMail;
use App\Mail\CustomerWelcomeMail;
use App\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class CustomerVerificationController extends Controller
{
    /**
     * Show email verification notice page.
     */
    public function notice(Request $request)
    {
        if (Auth::guard('customer')->check() && Auth::guard('customer')->user()->hasVerifiedEmail()) {
            return redirect()->route('home');
        }

        $email = $request->session()->get('pending_verification_email')
            ?: $request->query('email');

        if (!$email) {
            return redirect()->route('login');
        }

        $customer = Customer::where('email', $email)->first();

        if (!$customer) {
            return redirect()->route('login');
        }

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('login')->with('success', 'Your email address is already verified. Please sign in.');
        }

        $lockUntil = (int) Cache::get("customer_verify_cooldown:{$customer->id}", 0);
        $cooldownSeconds = max(0, $lockUntil - now()->timestamp);

        return Inertia::render('Frontend/Auth/VerifyEmail', [
            'email'           => $customer->email,
            'cooldownSeconds' => $cooldownSeconds,
        ]);
    }

    /**
     * Resend verification email, enforcing a 30-second cooldown.
     */
    public function resend(Request $request)
    {
        $email = $request->input('email')
            ?: $request->session()->get('pending_verification_email');

        if (!$email) {
            return redirect()->route('login')->with('error', 'Please provide a valid email address.');
        }

        $customer = Customer::where('email', $email)->first();

        if (!$customer) {
            // Generic message to avoid email enumeration
            return back()->with('success', 'If an account exists for this email, a verification link has been sent.');
        }

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('login')->with('success', 'Your email address is already verified. Please sign in.');
        }

        $lockKey = "customer_verify_cooldown:{$customer->id}";
        $lockUntil = (int) Cache::get($lockKey, 0);
        $remaining = $lockUntil - now()->timestamp;

        if ($remaining > 0) {
            return back()->with('warning', "Please wait {$remaining} second(s) before requesting another verification email.");
        }

        try {
            Mail::to($customer->email)->send(new CustomerVerifyEmailMail($customer));
            Cache::put($lockKey, now()->timestamp + 30, now()->addSeconds(35));
        } catch (\Throwable $e) {
            Log::error("Failed to send customer verification email to {$customer->email}: " . $e->getMessage());
            return back()->with('error', 'Unable to send verification email. Please try again in a few moments.');
        }

        $request->session()->put('pending_verification_email', $customer->email);

        return back()->with('success', 'A fresh verification link has been sent to your email address.');
    }

    /**
     * Mark the authenticated/unauthenticated customer's email as verified.
     */
    public function verify(Request $request, int $id, string $hash)
    {
        // Support both absolute signature check and relative signature check
        if (!$request->hasValidSignature() && !$request->hasValidSignature(false)) {
            $customer = Customer::find($id);
            if ($customer && !$customer->hasVerifiedEmail()) {
                $request->session()->put('pending_verification_email', $customer->email);
                return redirect()->route('verification.notice')->with('error', 'This verification link has expired or is invalid. Please request a new one.');
            }
            return redirect()->route('login')->with('error', 'This verification link has expired or is invalid.');
        }

        $customer = Customer::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($customer->getEmailForVerification()))) {
            abort(403, 'Invalid verification signature hash.');
        }

        $isNewlyVerified = false;

        if (!$customer->hasVerifiedEmail()) {
            $customer->markEmailAsVerified();
            $isNewlyVerified = true;

            try {
                Mail::to($customer->email)->queue(new CustomerWelcomeMail($customer));
            } catch (\Throwable $e) {
                Log::warning("Customer #{$customer->id} welcome email failed to queue: " . $e->getMessage());
            }

            ActivityLogger::customer(
                "Customer verified email address ({$customer->email})",
                ['customer_id' => $customer->id, 'email' => $customer->email],
                null,
                'customer'
            );
        }

        $request->session()->forget('pending_verification_email');

        return Inertia::render('Frontend/Auth/VerifySuccess', [
            'customer' => [
                'name'  => $customer->name,
                'email' => $customer->email,
            ],
            'alreadyVerified' => !$isNewlyVerified,
        ]);
    }
}

