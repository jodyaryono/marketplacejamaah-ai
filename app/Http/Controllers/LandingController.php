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
        // ── Locale resolution (?lang=en|id, persisted in session) ──
        if ($request->filled('lang') && in_array($request->lang, ['id', 'en'], true)) {
            session(['site_locale' => $request->lang]);
        }
        $locale = session('site_locale', 'id');
        if (!in_array($locale, ['id', 'en'], true)) $locale = 'id';

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
        $this->applyFilters($query, $request);
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
        $this->applyFilters($textQuery, $request);
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

        return view('landing', compact('listings', 'textListings', 'categories', 'totalActive', 'heroListings', 'topCategories', 'locale'));
    }

    public function marketingTools(Request $request)
    {
        $totalActive = Listing::where('status', 'active')->count();
        $totalSellers = \App\Models\Contact::whereHas('listings', fn($q) => $q->where('status', 'active'))->count();
        $totalCategories = Category::where('is_active', true)->count();

        // ── Locale resolution (?lang=en|id, persisted in session) ──
        if ($request->filled('lang') && in_array($request->lang, ['id', 'en'], true)) {
            session(['site_locale' => $request->lang]);
        }
        $locale = session('site_locale', 'id');
        if (!in_array($locale, ['id', 'en'], true)) $locale = 'id';

        return view('marketing-tools', compact('totalActive', 'totalSellers', 'totalCategories', 'locale'));
    }

    public function panduan(Request $request)
    {
        if ($request->filled('lang') && in_array($request->lang, ['id', 'en'], true)) {
            session(['site_locale' => $request->lang]);
        }
        return view('panduan');
    }

    public function releaseNotes(Request $request)
    {
        if ($request->filled('lang') && in_array($request->lang, ['id', 'en'], true)) {
            session(['site_locale' => $request->lang]);
        }
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
            ->orderByRaw("
                CASE WHEN (price IS NOT NULL AND price > 0)
                          OR (price_label IS NOT NULL AND price_label <> '')
                          OR (price_min IS NOT NULL AND price_min > 0)
                     THEN 0 ELSE 1 END,
                created_at DESC
            ");
        $this->deduplicateByContactTitle($query);

        if ($isText) {
            $query->where(function ($q) {
                $q->whereNull('media_urls')->orWhereRaw("media_urls::text = '[]'");
            });
        } else {
            $query->whereNotNull('media_urls')->whereRaw("media_urls::text != '[]'");
        }

        $this->applyFilters($query, $request);

        $perPage  = $isText ? 10 : 6;
        $listings = $query->paginate($perPage);
        $view     = $isText ? 'landing._iklan_baris' : 'landing._cards';
        $html     = view($view, ['listings' => $listings->items()])->render();

        return response()->json([
            'html'    => $html,
            'hasMore' => $listings->hasMorePages(),
            'total'   => $listings->total(),
        ]);
    }

    /**
     * Apply search/filter conditions shared by index() and loadMore().
     * Filters: search (keyword), category_id, min_price, max_price, location.
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $terms = array_filter(array_map('trim', preg_split('/[,\s]+/', strip_tags($request->search))));
            $query->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $q->where(function ($q2) use ($term) {
                        $q2->where('title', 'ilike', '%' . $term . '%')
                           ->orWhere('description', 'ilike', '%' . $term . '%');
                    });
                }
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->category_id);
        }

        if ($request->filled('min_price') && is_numeric($request->min_price)) {
            $query->where(function ($q) use ($request) {
                $q->where('price', '>=', (int) $request->min_price)
                  ->orWhere('price_min', '>=', (int) $request->min_price);
            });
        }

        if ($request->filled('max_price') && is_numeric($request->max_price)) {
            $query->where(function ($q) use ($request) {
                $q->where('price', '<=', (int) $request->max_price)
                  ->orWhere('price_max', '<=', (int) $request->max_price);
            });
        }

        if ($request->filled('location')) {
            $loc = strip_tags(trim($request->location));
            $query->where('location', 'ilike', '%' . $loc . '%');
        }
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
