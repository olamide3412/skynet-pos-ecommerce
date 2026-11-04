<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    /**
     * Handle newsletter subscription.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email:filter', 'max:255'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please provide a valid email address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first('email'),
            ], 422);
        }

        $email = strtolower(trim($request->input('email')));

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

