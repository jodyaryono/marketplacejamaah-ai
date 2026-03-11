<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Listing;
use App\Models\WhatsappGroup;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ListingController extends Controller
{
    public function index(Request $request)
    {
        $query = Listing::with(['category', 'group', 'contact'])->latest();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('group_id')) {
            $query->where('whatsapp_group_id', $request->group_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q
                    ->where('title', 'ilike', '%' . $request->search . '%')
                    ->orWhere('description', 'ilike', '%' . $request->search . '%');
            });
        }

        $listings = $query->paginate(20)->withQueryString();
        $categories = Category::where('is_active', true)->get();
        $groups = WhatsappGroup::where('is_active', true)->get();

        $stats = [
            'total' => Listing::count(),
            'today' => Listing::whereDate('created_at', today())->count(),
            'active' => Listing::where('status', 'active')->count(),
            'pending' => Listing::where('status', 'pending')->count(),
        ];

        return view('listings.index', compact('listings', 'categories', 'groups', 'stats'));
    }

    public function show(Listing $listing)
    {
        $listing->load(['message', 'category', 'group', 'contact']);
        return view('listings.show', compact('listing'));
    }

    public function updateStatus(Request $request, Listing $listing)
    {
        $request->validate(['status' => 'required|in:active,sold,expired,pending']);
        $listing->update(['status' => $request->status]);
        return back()->with('success', 'Status listing berhasil diperbarui.');
    }

    public function edit(Listing $listing)
    {
        $listing->load(['message', 'category', 'group', 'contact']);
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        return view('listings.edit', compact('listing', 'categories'));
    }

    public function update(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'price_label' => 'nullable|string|max:100',
            'location' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:50',
            'condition' => 'nullable|in:new,used,unknown',
            'category_id' => 'nullable|exists:categories,id',
            'status' => 'required|in:active,sold,expired,pending,inactive',
            'keep_media' => 'nullable|array',
            'keep_media.*' => 'nullable|string|max:500',
            'new_photos' => 'nullable|array|max:5',
            'new_photos.*' => 'file|mimes:jpg,jpeg,png,webp,mp4,webm,mov|max:20480',
        ]);

        // price / price_label mutual exclusion
        if (!empty($data['price_label'])) {
            $data['price'] = null;
        } elseif (isset($data['price']) && $data['price'] !== null) {
            $data['price_label'] = null;
        }

        // Kept existing media
        $keptMedia = array_values(array_filter($request->input('keep_media', []), 'strlen'));

        // ── Save newly uploaded files to disk ──────────────────────────────
        $newPhotos = [];  // [['url' => string, 'path' => string]]
        if ($request->hasFile('new_photos')) {
            $uploadDir = public_path('uploads/listings');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            foreach ($request->file('new_photos') as $file) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $destPath = $uploadDir . '/' . $filename;
                $file->move($uploadDir, $filename);
                $newPhotos[] = [
                    'url' => url('uploads/listings/' . $filename),
                    'path' => $destPath,
                ];
            }
        }

        // ── AI split: if 2+ new photos, analyse each for embedded price ────
        $priced = [];  // photos where AI detected a price
        $unpriced = [];  // photos without their own price

        if (count($newPhotos) >= 2) {
            foreach ($newPhotos as $photo) {
                // Skip video files from AI price analysis
                $ext = strtolower(pathinfo($photo['path'], PATHINFO_EXTENSION));
                if (in_array($ext, ['mp4', 'webm', 'mov'])) {
                    $unpriced[] = $photo;
                    continue;
                }
                $ai = $this->analyzePhotoForPrice($photo['path']);
                $photo['ai'] = $ai;
                if ($ai && !empty($ai['price'])) {
                    $priced[] = $photo;
                } else {
                    $unpriced[] = $photo;
                }
            }
        } else {
            $unpriced = $newPhotos;
        }

        $newCreatedCount = 0;

        if (count($priced) >= 2) {
            // ── Each priced image = its own listing ─────────────────────────
            // First priced image → update current listing
            $firstPriced = array_shift($priced);
            $ai = $firstPriced['ai'];

            $mediaForCurrent = array_merge(
                $keptMedia,
                array_map(fn($p) => $p['url'], $unpriced),
                [$firstPriced['url']]
            );
            $data['media_urls'] = $mediaForCurrent;

            // Fill in AI-extracted data if user left those fields empty
            if (empty($data['price']) && empty($data['price_label']) && !empty($ai['price'])) {
                $data['price'] = (int) $ai['price'];
                $data['price_label'] = null;
            }
            if (empty(trim($data['description'] ?? '')) && !empty($ai['description'])) {
                $data['description'] = $ai['description'];
            }
            if ($data['title'] === $listing->title && !empty($ai['title'])) {
                $data['title'] = $ai['title'];
            }

            // Remaining priced images → new listings
            foreach ($priced as $entry) {
                $this->createListingFromPhoto($listing, $entry);
                $newCreatedCount++;
            }
        } elseif ($request->has('keep_media') || $request->hasFile('new_photos')) {
            // Normal: all photos go to current listing
            $allMedia = array_merge($keptMedia, array_map(fn($p) => $p['url'], array_merge($unpriced, $priced)));
            $data['media_urls'] = $allMedia ?: $listing->media_urls;
        }

        unset($data['keep_media'], $data['new_photos']);
        $listing->update($data);

        $msg = 'Listing berhasil diperbarui.';
        if ($newCreatedCount > 0) {
            $msg .= " {$newCreatedCount} listing baru otomatis dibuat dari gambar beriharga.";
        }
        return redirect()->route('listings.show', $listing)->with('success', $msg);
    }

    /**
     * Analyse a local image file with Gemini and return parsed array (or null).
     */
    private function analyzePhotoForPrice(string $localPath): ?array
    {
        try {
            /** @var GeminiService $gemini */
            $gemini = app(GeminiService::class);
            $base64 = base64_encode(file_get_contents($localPath));
            $mime = mime_content_type($localPath) ?: 'image/jpeg';
            $categories = Category::pluck('name')->implode(', ');

            $prompt = <<<PROMPT
                Kamu adalah AI classifier untuk marketplace Indonesia.
                Lihat gambar ini dan tentukan apakah ada HARGA yang tertera.
                Kategori tersedia: {$categories}

                Jawab HANYA JSON valid (tanpa komentar):
                {
                  "is_ad": true/false,
                  "price": angka bulat saja jika harga tertera di gambar, atau null,
                  "price_label": "label harga asli jika tidak berupa angka tunggal, atau null",
                  "title": "judul produk singkat maks 100 karakter, atau null",
                  "description": "deskripsi lengkap produk dari gambar, atau null",
                  "category": "nama kategori yang paling sesuai, atau null",
                  "condition": "new/used/unknown"
                }
                PROMPT;

            $raw = $gemini->analyzeImageWithText($base64, $mime, $prompt);
            if (!$raw) {
                return null;
            }
            $clean = preg_replace('/```json\s*/i', '', $raw);
            $clean = preg_replace('/```\s*/i', '', $clean);
            $parsed = json_decode(trim($clean), true);
            return is_array($parsed) ? $parsed : null;
        } catch (\Exception $e) {
            Log::warning('ListingController: photo AI analysis failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a new listing derived from a priced split-image.
     */
    private function createListingFromPhoto(Listing $source, array $photo): void
    {
        $ai = $photo['ai'] ?? [];
        $categoryId = $source->category_id;

        if (!empty($ai['category'])) {
            $cat = Category::where('name', $ai['category'])->first();
            if ($cat) {
                $categoryId = $cat->id;
            }
        }

        Listing::create([
            'title' => $ai['title'] ?? $source->title,
            'description' => $ai['description'] ?? $source->description,
            'price' => isset($ai['price']) ? (int) $ai['price'] : null,
            'price_label' => $ai['price_label'] ?? null,
            'location' => $source->location,
            'contact_number' => $source->contact_number,
            'condition' => $ai['condition'] ?? $source->condition,
            'category_id' => $categoryId,
            'whatsapp_group_id' => $source->whatsapp_group_id,
            'media_urls' => [$photo['url']],
            'status' => 'active',
            'source' => 'admin_split',
            'message_id' => $source->message_id,
        ]);
    }
}
