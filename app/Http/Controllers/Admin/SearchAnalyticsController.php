<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // 1. Aggregated top searches
        $topSearches = SearchLog::select('normalized_query')
            ->selectRaw('COUNT(*) as total_searches')
            ->selectRaw('MAX(results_count) as last_results_count')
            ->selectRaw('MAX(created_at) as last_searched_at')
            ->groupBy('normalized_query')
            ->orderByDesc('total_searches')
            ->paginate(15)
            ->withQueryString();

        // 2. Metrics summary
        $totalSearchesCount = SearchLog::count();
        $uniqueTermsCount   = SearchLog::distinct('normalized_query')->count('normalized_query');
        $zeroResultsCount   = SearchLog::where('results_count', 0)->count();
        $todaySearchesCount = SearchLog::whereDate('created_at', today())->count();

        // 3. Zero result searches (Missed opportunities for inventory)
        $zeroResultSearches = SearchLog::select('normalized_query')
            ->selectRaw('COUNT(*) as search_count')
            ->selectRaw('MAX(created_at) as last_searched_at')
            ->where('results_count', 0)
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit(10)
            ->get();

        // 4. Recent searches stream
        $recentSearches = SearchLog::with('customer:id,name,email')
            ->latest()
            ->limit(20)
            ->get(['id', 'query', 'results_count', 'customer_id', 'ip_address', 'created_at']);

        return Inertia::render('Admin/Reports/SearchAnalytics', [
            'top_searches'          => $topSearches,
            'zero_result_searches'  => $zeroResultSearches,
            'recent_searches'       => $recentSearches,
            'metrics' => [
                'total_searches'   => $totalSearchesCount,
                'unique_terms'     => $uniqueTermsCount,
                'zero_results'     => $zeroResultsCount,
                'today_searches'   => $todaySearchesCount,
            ],
        ]);
    }
}

