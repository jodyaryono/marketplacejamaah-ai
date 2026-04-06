<?php

namespace App\Agents;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Support\Str;

class SearchAgent
{
    private string $baseUrl;

    public function __construct(
        private GeminiService $gemini,
    ) {
        $this->baseUrl = rtrim(config('app.url'), '/');
    }

    public function searchProducts(string $userQuery, ?string $category, int $limit): string
    {
        $listings = $this->ragRetrieveListings($userQuery, $category)->take($limit);

        if ($listings->isEmpty()) {
            $what = $category ?? $userQuery;
            return "😔 Maaf, belum ada iklan *{$what}* yang tersedia saat ini.\n\n_Coba cari dengan kata lain._";
        }

        $lines = ["🛍️ *{$listings->count()} Produk Ditemukan*\n"];
        foreach ($listings as $i => $listing) {
            $seller = $listing->contact?->name ?? $listing->contact_name ?? 'Penjual';
            $price  = $listing->price_formatted;
            $loc    = $listing->location ? " 📍_{$listing->location}_" : '';
            $link   = "{$this->baseUrl}/p/{$listing->id}";
            $lines[] = ($i + 1) . ". *{$listing->title}*\n"
                . "   💰 {$price}{$loc}\n"
                . "   👤 {$seller}\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n_Kunjungi link untuk detail & info kontak penjual._";
        return implode("\n", $lines);
    }

    public function searchSellers(string $userQuery, ?string $category, int $limit): string
    {
        $listings = $this->ragRetrieveListings($userQuery, $category, 100);

        if ($listings->isEmpty()) {
            $what = $category ?? $userQuery;
            return "😔 Maaf, belum ada penjual *{$what}* yang terdaftar saat ini.\n\n_Coba cari dengan kata lain._";
        }

        $sellers = $listings
            ->filter(fn($l) => $l->contact !== null)
            ->groupBy('contact_id')
            ->map(fn($group) => [
                'contact'       => $group->first()->contact,
                'listing_count' => $group->count(),
                'products'      => $group->pluck('title')->take(3)->implode(', '),
            ])
            ->sortByDesc('listing_count')
            ->take($limit)
            ->values();

        if ($sellers->isEmpty()) {
            return '😔 Maaf, belum ada penjual yang relevan ditemukan.';
        }

        $lines = ["🏪 *Penjual yang Relevan*\n"];
        foreach ($sellers as $i => $s) {
            $contact = $s['contact'];
            $name    = $contact->name ?? $contact->phone_number;
            $link    = "{$this->baseUrl}/u/{$contact->phone_number}";
            $lines[] = ($i + 1) . ". *{$name}*\n"
                . "   📦 {$s['listing_count']} iklan\n"
                . "   🏷️ _{$s['products']}_\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n_Klik link untuk lihat semua iklan penjual._";
        return implode("\n", $lines);
    }

    public function listCategories(): string
    {
        $categories = Category::where('is_active', true)
            ->withCount(['listings' => fn($q) => $q->where('status', 'active')])
            ->orderByDesc('listings_count')
            ->get();

        if ($categories->isEmpty()) {
            return 'Belum ada kategori produk terdaftar.';
        }

        $lines = ["📂 *Kategori Produk Marketplace Jamaah*\n"];
        foreach ($categories as $cat) {
            $lines[] = "• *{$cat->name}* ({$cat->listings_count} iklan)";
        }
        $lines[] = "\n_Ketik nama kategori untuk mencari penjual atau produk._\n_Contoh: \"cari penjual makanan\" atau \"tampilkan produk hijab\"_";

        return implode("\n", $lines);
    }

    public function myListings(string $phoneNumber, int $limit): string
    {
        $contact = Contact::where('phone_number', $phoneNumber)->first();
        if (!$contact) {
            return '❌ Nomor kamu belum terdaftar. Kirim pesan di grup Marketplace Jamaah terlebih dahulu.';
        }

        $listings = Listing::where('contact_id', $contact->id)
            ->orWhere('contact_number', $phoneNumber)
            ->latest('source_date')
            ->limit($limit)
            ->get();

        if ($listings->isEmpty()) {
            return "📭 Kamu belum memiliki iklan aktif di Marketplace Jamaah.\n\n_Posting iklan di grup untuk mulai berjualan!_";
        }

        $lines = ["📋 *Iklan Kamu ({$listings->count()} terakhir)*\n"];
        foreach ($listings as $i => $listing) {
            $status = match ($listing->status) {
                'active' => '🟢',
                'sold'   => '✅',
                default  => '🟡',
            };
            $link    = "{$this->baseUrl}/p/{$listing->id}";
            $lines[] = ($i + 1) . ". {$status} *#{$listing->id}* — *{$listing->title}*\n"
                . "   💰 {$listing->price_formatted}\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n✏️ _Mau edit? Ketik *edit #<nomor>*, misal: *edit #{$listings->first()->id}*_";

        return implode("\n", $lines);
    }

    /**
     * RAG: retrieve candidate listings from DB, then ask Gemini to rank
     * the most semantically relevant ones based on the full user query.
     * Gemini result is cached by (query + category) to save API calls.
     */
    public function ragRetrieveListings(string $userQuery, ?string $category, int $candidateLimit = 100): \Illuminate\Support\Collection
    {
        $q = Listing::with(['contact', 'category'])->where('status', 'active');
        if ($category) {
            $q->whereHas('category', fn($c) => $c->where('name', 'ilike', "%{$category}%"));
        }
        $candidates = $q->latest('source_date')->limit($candidateLimit)->get();

        // If category filter yields nothing, expand to all listings
        if ($candidates->isEmpty() && $category) {
            $candidates = Listing::with(['contact', 'category'])
                ->where('status', 'active')
                ->latest('source_date')
                ->limit($candidateLimit)
                ->get();
        }

        if ($candidates->isEmpty()) {
            return collect();
        }

        // Too few candidates — return directly without Gemini
        if ($candidates->count() <= 3) {
            return $candidates;
        }

        $context = $candidates->map(fn($l) =>
            "ID:{$l->id} | {$l->title} | Kategori:{$l->category?->name} | Lokasi:{$l->location} | "
            . Str::limit($l->description ?? '', 80))->implode("\n");

        $promptTemplate = Setting::get('prompt_bot_rag_relevance', 'Query: "{userQuery}" Iklan: {context} Jawab JSON: {"ids":[]}');
        $prompt = str_replace(['{userQuery}', '{context}'], [$userQuery, $context], $promptTemplate);

        // Cache RAG relevance for 10 minutes (catalog changes slowly)
        $result = $this->gemini->generateJson($prompt, cacheTtl: 10);

        if (is_null($result)) {
            return $candidates;
        }

        $ids = $result['ids'] ?? [];
        if (empty($ids)) {
            return collect();
        }

        $byId = $candidates->keyBy('id');
        return collect($ids)
            ->map(fn($id) => $byId->get((int) $id))
            ->filter()
            ->values();
    }
}
