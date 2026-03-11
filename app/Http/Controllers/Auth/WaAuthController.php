<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\WaLoginToken;
use App\Services\WhacenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class WaAuthController extends Controller
{
    private const COOKIE_NAME = 'wamkt_auth';
    private const COOKIE_DAYS = 180;  // 6 months
    private const SESSION_KEY = 'wa_contact_id';

    // ── Show login form ──────────────────────────────────────────────────
    public function showForm()
    {
        if ($this->resolveContactFromSession(request())) {
            return redirect()->route('member.dashboard');
        }
        return view('auth.wa-login');
    }

    // ── Step 1: request OTP ──────────────────────────────────────────────
    public function requestOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:9', 'max:15', 'regex:/^[0-9]+$/'],
        ], ['phone.regex' => 'Masukkan nomor HP tanpa spasi atau tanda hubung.']);

        $phone = $this->normalizePhone($request->phone);

        // Rate limit: max 3 OTP requests per phone per 5 minutes
        $key = 'wa-otp:' . $phone;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['phone' => "Terlalu banyak permintaan. Coba lagi dalam {$seconds} detik."]);
        }
        RateLimiter::hit($key, 300);

        [$record, $otp] = WaLoginToken::generateOtp($phone, $request->ip());

        // Send OTP via WhatsApp
        try {
            $wa = app(WhacenterService::class);
            $wa->sendMessage($phone, "🔐 *Kode OTP Marketplace Jamaah*\n\nKode verifikasi Anda:\n\n*{$otp}*\n\n_Kode berlaku 10 menit. Jangan bagikan ke siapapun._");
        } catch (\Exception $e) {
            Log::error('WaAuthController: failed to send OTP', ['error' => $e->getMessage(), 'phone' => $phone]);
            return back()->withErrors(['phone' => 'Gagal mengirim OTP via WhatsApp. Coba beberapa saat lagi.']);
        }

        return back()->with([
            'otp_sent' => true,
            'otp_phone' => $phone,
        ]);
    }

    // ── Step 2: verify OTP ───────────────────────────────────────────────
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]+$/'],
        ]);

        $phone = $this->normalizePhone($request->phone);
        $record = WaLoginToken::where('phone_number', $phone)->first();

        if (!$record) {
            return back()->withErrors(['otp' => 'Sesi OTP tidak ditemukan. Minta kode baru.'])->withInput();
        }

        // Increment attempt count first to prevent brute force
        $record->increment('otp_attempts');

        if (!$record->isOtpValid($request->otp)) {
            return back()->withErrors(['otp' => 'Kode OTP salah atau sudah kadaluarsa.'])->withInput();
        }

        // OTP valid — activate long-lived remember token
        $rememberToken = $record->activateRememberToken();

        // Ensure contact record exists
        $contact = Contact::firstOrCreate(
            ['phone_number' => $phone],
            ['name' => $phone]
        );

        // Store contact in session
        $request->session()->put(self::SESSION_KEY, $contact->id);
        $request->session()->regenerate();

        // Set 6-month remember cookie
        $cookie = cookie(
            self::COOKIE_NAME,
            $rememberToken,
            self::COOKIE_DAYS * 24 * 60,  // minutes
            '/',
            null,
            true,  // secure
            true,  // httpOnly
            false,
            'Lax'
        );

        return redirect()->route('member.dashboard')->withCookie($cookie);
    }

    // ── Member dashboard (public, WA-authenticated) ──────────────────────
    public function dashboard(Request $request)
    {
        $contact = $this->resolveContactFromSession($request);
        if (!$contact) {
            return redirect()->route('wa.login');
        }

        $listings = \App\Models\Listing::with('category')
            ->where(function ($q) use ($contact) {
                $q
                    ->where('contact_id', $contact->id)
                    ->orWhere('contact_number', $contact->phone_number);
            })
            ->latest('source_date')
            ->paginate(12);

        return view('member.dashboard', compact('contact', 'listings'));
    }

    // ── Edit listing ──────────────────────────────────────────────────────
    public function editListing(int $id, Request $request)
    {
        $contact = $this->resolveContactFromSession($request);
        if (!$contact) {
            return redirect()->route('wa.login');
        }

        $listing = Listing::with('category')->findOrFail($id);

        // Ownership check
        if ($listing->contact_id !== $contact->id && $listing->contact_number !== $contact->phone_number) {
            abort(403, 'Anda tidak berhak mengedit iklan ini.');
        }

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('member.edit-listing', compact('contact', 'listing', 'categories'));
    }

    // ── Update listing ────────────────────────────────────────────────────
    public function updateListing(int $id, Request $request)
    {
        $contact = $this->resolveContactFromSession($request);
        if (!$contact) {
            return redirect()->route('wa.login');
        }

        $listing = Listing::findOrFail($id);

        // Ownership check
        if ($listing->contact_id !== $contact->id && $listing->contact_number !== $contact->phone_number) {
            abort(403, 'Anda tidak berhak mengedit iklan ini.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_label' => ['nullable', 'string', 'max:100'],
            'condition' => ['required', 'in:new,used,unknown'],
            'status' => ['required', 'in:active,sold'],
            'location' => ['nullable', 'string', 'max:255'],
            'new_photos' => ['nullable', 'array', 'max:5'],
            'new_photos.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,webm,mov', 'max:20480'],
            'remove_media' => ['nullable', 'array'],
            'remove_media.*' => ['nullable', 'string', 'max:500'],
        ]);

        // ── Process media changes ───────────────────────────────────────
        $existingMedia = is_array($listing->media_urls) ? $listing->media_urls : [];

        // Remove photos that the user X'd out
        if (!empty($validated['remove_media'])) {
            $toRemove = $validated['remove_media'];
            $existingMedia = array_values(array_filter(
                $existingMedia,
                fn($m) => !in_array($m, $toRemove, true)
            ));
        }

        // Store newly uploaded photos
        if ($request->hasFile('new_photos')) {
            $uploadDir = public_path('uploads/listings');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            foreach ($request->file('new_photos') as $file) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($uploadDir, $filename);
                $existingMedia[] = url('uploads/listings/' . $filename);
            }
        }

        $listing->media_urls = $existingMedia ?: $listing->media_urls;

        // ── Basic fields ────────────────────────────────────────────────
        // If price_label is set, clear numeric price fields; otherwise set price
        if (!empty($validated['price_label'])) {
            $listing->price_label = $validated['price_label'];
            $listing->price = null;
            $listing->price_min = null;
            $listing->price_max = null;
        } else {
            $listing->price = $validated['price'] ?? null;
            $listing->price_min = null;
            $listing->price_max = null;
            $listing->price_label = null;
        }

        $listing->title = $validated['title'];
        $listing->description = $validated['description'] ?? $listing->description;
        $listing->category_id = $validated['category_id'] ?? $listing->category_id;
        $listing->condition = $validated['condition'];
        $listing->status = $validated['status'];
        $listing->location = $validated['location'] ?? $listing->location;
        $listing->save();

        return redirect()->route('member.dashboard')->with('success', 'Iklan berhasil diperbarui! ✅');
    }

    // ── Logout ───────────────────────────────────────────────────────────
    public function logout(Request $request)
    {
        $token = $request->cookie(self::COOKIE_NAME);
        if ($token) {
            WaLoginToken::where('remember_token', $token)->update([
                'remember_token' => null,
                'remember_expires_at' => null,
            ]);
        }

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        $expiredCookie = cookie()->forget(self::COOKIE_NAME);
        return redirect()->route('landing')->withCookie($expiredCookie)->with('success', 'Kamu berhasil logout.');
    }

    // ── Middleware helper: auto-login from cookie ─────────────────────────
    public static function resolveFromCookie(Request $request): ?Contact
    {
        $token = $request->cookie(self::COOKIE_NAME);
        if (!$token) {
            return null;
        }

        $record = WaLoginToken::where('remember_token', $token)->first();
        if (!$record || !$record->isRememberTokenValid($token)) {
            return null;
        }

        $record->update(['last_used_at' => now()]);
        return Contact::where('phone_number', $record->phone_number)->first();
    }

    // ── Private helpers ──────────────────────────────────────────────────
    private function resolveContactFromSession(Request $request): ?Contact
    {
        // Check session first
        $contactId = $request->session()->get(self::SESSION_KEY);
        if ($contactId) {
            $contact = Contact::find($contactId);
            if ($contact) {
                return $contact;
            }
        }

        // Try remember cookie
        $contact = self::resolveFromCookie($request);
        if ($contact) {
            $request->session()->put(self::SESSION_KEY, $contact->id);
            return $contact;
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        } elseif (str_starts_with($phone, '8')) {
            $phone = '62' . $phone;
        }
        return $phone;
    }
}
