<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = ProductReview::with([
            'product:id,name,image,url_key',
            'customer:id,name,email',
            'order:id,tracking_number',
        ]);

        if ($request->filled('status')) {
            if ($request->status === 'approved') {
                $query->where('is_approved', true);
            } elseif ($request->status === 'pending') {
                $query->where('is_approved', false);
            }
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                  ->orWhereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            });
        }

        $reviews = $query->latest()->paginate(25)->withQueryString();

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => [
                'search' => $request->search ?? '',
                'status' => $request->status ?? '',
                'rating' => $request->rating ?? '',
            ],
            'stats'   => [
                'total'    => ProductReview::count(),
                'approved' => ProductReview::where('is_approved', true)->count(),
                'pending'  => ProductReview::where('is_approved', false)->count(),
                'average'  => round((float) (ProductReview::avg('rating') ?: 5.0), 1),
            ],
        ]);
    }

    public function toggleApproval(ProductReview $review)
    {
        $review->update(['is_approved' => !$review->is_approved]);

        $status = $review->is_approved ? 'approved and published' : 'hidden';
        return back()->with('success', "Review #{$review->id} has been {$status}.");
    }

    public function destroy(ProductReview $review)
    {
        $id = $review->id;
        $review->delete();

        return back()->with('success', "Review #{$id} deleted successfully.");
    }
}

