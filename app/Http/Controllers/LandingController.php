<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request)
    {
        // ── Produk dengan foto/video ────────────────────────────────────────
        $query = Listing::with(['category', 'contact'])
            ->where('status', 'active')
            ->whereNotNull('media_urls')
            ->whereRaw("media_urls::text != '[]'")
            ->latest();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $terms = array_filter(array_map('trim', preg_split('/[,\s]+/', $request->search)));
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->where(function ($q2) use ($term) {
                        $q2->where('title', 'ilike', '%' . $term . '%')
                           ->orWhere('description', 'ilike', '%' . $term . '%');
                    });
                }
            });
        }
        $listings = $query->paginate(6)->withQueryString();

        // ── Iklan baris (tanpa foto/video) ─────────────────────────────────
        $textQuery = Listing::with(['category', 'contact'])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('media_urls')->orWhereRaw("media_urls::text = '[]'");
            })
            ->latest();

        if ($request->filled('category_id')) {
            $textQuery->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $terms = array_filter(array_map('trim', preg_split('/[,\s]+/', $request->search)));
            $textQuery->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->where(function ($q2) use ($term) {
                        $q2->where('title', 'ilike', '%' . $term . '%')
                           ->orWhere('description', 'ilike', '%' . $term . '%');
                    });
                }
            });
        }
        $textListings = $textQuery->paginate(10)->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $totalActive = Listing::where('status', 'active')->count();

        // Prioritize video listings in hero slider
        $heroVideos = Listing::with(['contact', 'category'])
            ->where('status', 'active')
            ->whereNotNull('media_urls')
            ->whereRaw("media_urls::text != '[]'")
            ->where(function ($q) {
                $q->whereRaw("media_urls::text LIKE '%.mp4%'")
                  ->orWhereRaw("media_urls::text LIKE '%.mov%'")
                  ->orWhereRaw("media_urls::text LIKE '%.webm%'");
            })
            ->inRandomOrder()
            ->limit(6)
            ->get();

        $remaining = max(0, 6 - $heroVideos->count());
        $heroImages = $remaining > 0
            ? Listing::with(['contact', 'category'])
                ->where('status', 'active')
                ->whereNotNull('media_urls')
                ->whereRaw("media_urls::text != '[]'")
                ->where(function ($q) {
                    $q->whereRaw("media_urls::text NOT LIKE '%.mp4%'")
                      ->whereRaw("media_urls::text NOT LIKE '%.mov%'")
                      ->whereRaw("media_urls::text NOT LIKE '%.webm%'");
                })
                ->inRandomOrder()
                ->limit($remaining)
                ->get()
            : collect();

        $heroListings = $heroVideos->concat($heroImages)->shuffle();

        $topCategories = Category::where('is_active', true)
            ->withCount(['listings' => fn($q) => $q->where('status', 'active')])
            ->orderByDesc('listings_count')
            ->limit(5)
            ->get();

        return view('landing', compact('listings', 'textListings', 'categories', 'totalActive', 'heroListings', 'topCategories'));
    }

    public function marketingTools()
    {
        $totalActive = Listing::where('status', 'active')->count();
        $totalSellers = \App\Models\Contact::whereHas('listings', fn($q) => $q->where('status', 'active'))->count();
        $totalCategories = Category::where('is_active', true)->count();
        return view('marketing-tools', compact('totalActive', 'totalSellers', 'totalCategories'));
    }

    public function releaseNotes()
    {
        return view('release-notes');
    }

    public function loadMore(Request $request)
    {
        if (!$request->ajax() && !$request->wantsJson()) {
            abort(404);
        }

        $isText = $request->input('type') === 'text';

        $query = Listing::with(['category', 'contact'])
            ->where('status', 'active')
            ->latest();

        if ($isText) {
            $query->where(function ($q) {
                $q->whereNull('media_urls')->orWhereRaw("media_urls::text = '[]'");
            });
        } else {
            $query->whereNotNull('media_urls')->whereRaw("media_urls::text != '[]'");
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }
        if ($request->filled('search')) {
            $search = strip_tags($request->search);
            $terms = array_filter(array_map('trim', preg_split('/[,\s]+/', $search)));
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->where(function ($q2) use ($term) {
                        $q2->where('title', 'ilike', '%' . $term . '%')
                           ->orWhere('description', 'ilike', '%' . $term . '%');
                    });
                }
            });
        }

        $perPage = $isText ? 10 : 6;
        $listings = $query->paginate($perPage);
        $view = $isText ? 'landing._iklan_baris' : 'landing._cards';
        $html = view($view, ['listings' => $listings->items()])->render();

        return response()->json([
            'html' => $html,
            'hasMore' => $listings->hasMorePages(),
            'total' => $listings->total(),
        ]);
    }
}
