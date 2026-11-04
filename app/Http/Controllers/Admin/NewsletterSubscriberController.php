<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscriberController extends Controller
{
    /**
     * Display a listing of newsletter subscribers.
     */
    public function index(Request $request)
    {
        $query = NewsletterSubscriber::query()->latest();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where('email', 'like', "%{$search}%");
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $subscribers = $query->paginate(20)->withQueryString();

        $stats = [
            'total' => NewsletterSubscriber::count(),
            'active' => NewsletterSubscriber::where('status', 'subscribed')->count(),
            'unsubscribed' => NewsletterSubscriber::where('status', 'unsubscribed')->count(),
            'new_this_week' => NewsletterSubscriber::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return Inertia::render('Admin/Newsletter/Index', [
            'subscribers' => $subscribers,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', 'all'),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Delete a subscriber.
     */
    public function destroy(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed successfully.');
    }

    /**
     * Toggle subscriber status between subscribed and unsubscribed.
     */
    public function toggleStatus(NewsletterSubscriber $subscriber)
    {
        $newStatus = $subscriber->status === 'subscribed' ? 'unsubscribed' : 'subscribed';
        $subscriber->update(['status' => $newStatus]);

        return back()->with('success', "Subscriber status updated to {$newStatus}.");
    }

    /**
     * Export all subscribers as a CSV file.
     */
    public function exportCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="newsletter_subscribers_' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Email', 'Status', 'Source', 'IP Address', 'Subscribed At', 'Created At']);

            NewsletterSubscriber::chunk(200, function ($subscribers) use ($handle) {
                foreach ($subscribers as $s) {
                    fputcsv($handle, [
                        $s->id,
                        $s->email,
                        $s->status,
                        $s->source,
                        $s->ip_address,
                        $s->subscribed_at ? $s->subscribed_at->toDateTimeString() : '',
                        $s->created_at->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}

