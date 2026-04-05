<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index(Request $request)
    {
        $perPageMedia = (int) Setting::get('landing_listings_with_media', 6);
        $perPageText  = (int) Setting::get('landing_listings_text', 10);

        // ── Produk dengan foto/video ────────────────────────────────────────
        $query = Listing::with(['category', 'contact'])
            ->where('status', 'active')
            ->whereNotNull('media_urls')
            ->whereRaw("media_urls::text != '[]'")
            ->orderByRaw("
                CASE WHEN (price IS NOT NULL AND price > 0)
                          OR (price_label IS NOT NULL AND price_label <> '')
                          OR (price_min IS NOT NULL AND price_min > 0)
                     THEN 0 ELSE 1 END,
                created_at DESC
            ");
        $this->deduplicateByContactTitle($query);

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
        $listings = $query->paginate($perPageMedia)->withQueryString();

        // ── Iklan baris (tanpa foto/video) ─────────────────────────────────
        $textQuery = Listing::with(['category', 'contact'])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('media_urls')->orWhereRaw("media_urls::text = '[]'");
            })
            ->orderByRaw("
                CASE WHEN (price IS NOT NULL AND price > 0)
                          OR (price_label IS NOT NULL AND price_label <> '')
                          OR (price_min IS NOT NULL AND price_min > 0)
                     THEN 0 ELSE 1 END,
                created_at DESC
            ");
        $this->deduplicateByContactTitle($textQuery);

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
        $textListings = $textQuery->paginate($perPageText)->withQueryString();

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

    public function panduan()
    {
        return view('panduan');
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
        $this->deduplicateByContactTitle($query);

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

    /**
     * Show only the most recent listing per seller+title to avoid duplicate ads.
     */
    private function deduplicateByContactTitle(Builder $query): void
    {
        $query->whereIn('id', function ($sub) {
            $sub->selectRaw('MAX(id)')
                ->from('listings')
                ->where('status', 'active')
                ->groupBy('contact_id', \DB::raw('lower(trim(title))'));
        });
    }
}
