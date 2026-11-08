<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\StoreSetting;
use App\Rules\Turnstile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    /**
     * Common disposable email domains to reject.
     */
    protected const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'tempmail.com', 'temp-mail.org', '10minutemail.com',
        'guerrillamail.com', 'sharklasers.com', 'throwawaymail.com', 'yopmail.com',
        'trashmail.com', 'getairmail.com', 'dispostable.com', 'fakeinbox.com',
    ];

    /**
     * Handle newsletter subscription with layered bot protection.
     */
    public function subscribe(Request $request): JsonResponse
    {
        // ── 1. Honeypot Trap (Silent Discard) ──────────────────────────────────
        // If a bot filled out the hidden field, return fake success so it stops trying
        if ($request->filled('b_hp_contact_secondary') || $request->filled('website_url')) {
            Log::info("Newsletter bot trapped via honeypot from IP {$request->ip()}");
            return response()->json([
                'success' => true,
                'already_subscribed' => false,
                'message' => 'Thank you for subscribing! Check your inbox soon for updates & offers.',
            ]);
        }

        // ── 2. Time-Delay Lock (Submissions faster than 1.2s are automated bots) ──
        $renderedAt = $request->input('_rendered_at');
        if ($renderedAt && is_numeric($renderedAt)) {
            $elapsedSeconds = (microtime(true) * 1000 - (float) $renderedAt) / 1000;
            // If submitted in under 1.2 seconds, it was filled by an automated script
            if ($elapsedSeconds > 0 && $elapsedSeconds < 1.2) {
                Log::info("Newsletter bot trapped via speed detection ({$elapsedSeconds}s) from IP {$request->ip()}");
                return response()->json([
                    'success' => true,
                    'already_subscribed' => false,
                    'message' => 'Thank you for subscribing! Check your inbox soon for updates & offers.',
                ]);
            }
        }

        // ── 3. Turnstile CAPTCHA (if enabled in Store Settings) ───────────────
        $turnstileEnabled = StoreSetting::getBool('turnstile_enabled', true);

        $rules = [
            'email' => ['required', 'string', 'email:filter', 'max:255'],
        ];

        if ($turnstileEnabled && $request->filled('cf_turnstile_response')) {
            $rules['cf_turnstile_response'] = [new Turnstile()];
        }

        $validator = Validator::make($request->all(), $rules, [
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'Please provide a valid email address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('email') ?? $validator->errors()->first('cf_turnstile_response'),
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));

        // ── 4. Disposable Domain Check ───────────────────────────────────────
        $domain = substr(strrchr($email, "@"), 1);
        if ($domain && in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid, permanent email address.',
            ], 422);
        }

        $subscriber = NewsletterSubscriber::where('email', $email)->first();

        if ($subscriber) {
            if ($subscriber->status === 'subscribed') {
                return response()->json([
                    'success' => true,
                    'already_subscribed' => true,
                    'message' => "You're already subscribed! Look out for our upcoming offers.",
                ]);
            }

            // Reactivate subscription if was unsubscribed
            $subscriber->update([
                'status' => 'subscribed',
                'subscribed_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'already_subscribed' => false,
                'message' => 'Welcome back! Your subscription has been reactivated.',
            ]);
        }

        NewsletterSubscriber::create([
            'email' => $email,
            'status' => 'subscribed',
            'source' => $request->input('source', 'footer'),
            'ip_address' => $request->ip(),
            'subscribed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'already_subscribed' => false,
            'message' => 'Thank you for subscribing! Check your inbox soon for updates & offers.',
        ]);
    }
}

