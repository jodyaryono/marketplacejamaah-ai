<?php

namespace App\Http\Controllers;

use App\Agents\MemberOnboardingAgent;
use App\Jobs\SendOnboardingDmJob;
use App\Models\Contact;
use App\Services\WhacenterService;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::withCount(['messages', 'listings'])->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q
                    ->where('phone_number', 'ilike', '%' . $request->search . '%')
                    ->orWhere('name', 'ilike', '%' . $request->search . '%');
            });
        }

        $contacts = $query->paginate(25)->withQueryString();

        $stats = [
            'total' => Contact::count(),
            'new_today' => Contact::whereDate('created_at', today())->count(),
            'sellers' => Contact::where('ad_count', '>', 0)->count(),
            'active_week' => Contact::where('last_seen', '>=', now()->subDays(7))->count(),
        ];

        return view('contacts.index', compact('contacts', 'stats'));
    }

    public function show(Contact $contact)
    {
        $listings = $contact->listings()->with('category')->latest()->limit(10)->get();
        $messages = \App\Models\Message::where('sender_number', $contact->phone_number)
            ->with('group')
            ->latest()
            ->limit(20)
            ->get();
        return view('contacts.show', compact('contact', 'messages', 'listings'));
    }

    public function update(Request $request, Contact $contact)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:50|unique:contacts,phone_number,' . $contact->id,
            'member_role' => 'nullable|in:seller,buyer,both',
            'is_blocked' => 'boolean',
        ]);

        $contact->update($request->only('name', 'phone_number', 'member_role', 'is_blocked'));

        return back()->with('success', 'Kontak berhasil diperbarui.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();
        return redirect()->route('contacts.index')->with('success', 'Kontak berhasil dihapus.');
    }

    public function sendMessage(Request $request, Contact $contact)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        try {
            $wa = app(WhacenterService::class);
            $wa->sendMessage($contact->phone_number, $request->message);
            return back()->with('success', 'Pesan berhasil dikirim ke ' . ($contact->name ?: $contact->phone_number) . '.');
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal kirim pesan: ' . $e->getMessage());
        }
    }

    public function resendOnboarding(Contact $contact)
    {
        try {
            $agent = app(MemberOnboardingAgent::class);
            $agent->resendOnboarding($contact);
            return response()->json([
                'success' => true,
                'message' => 'DM onboarding berhasil dikirim ulang ke ' . ($contact->name ?: $contact->phone_number),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal kirim: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function approve(Contact $contact)
    {
        try {
            $wa = app(WhacenterService::class);
            $status = $contact->onboarding_status ?? '';

            // Parse group approval status: pending_group_approval:{groupJid}:{requesterJid}
            if (str_starts_with($status, 'pending_group_approval:')) {
                $suffix = substr($status, strlen('pending_group_approval:'));
                $parts = explode(':', $suffix, 2);
                $groupJid = $parts[0] ?? '';
                $requesterJid = $parts[1] ?? '';

                if ($groupJid) {
                    $wa->approveMembership($groupJid, $contact->phone_number, $requesterJid);
                }
            }

            $contact->update([
                'is_registered' => true,
                'onboarding_status' => 'completed',
            ]);

            // Send welcome DM
            $name = $contact->name ?: 'Anggota';
            $wa->sendMessage($contact->phone_number, "✅ Selamat datang *{$name}*! Anda telah disetujui masuk grup MarketplaceJamaah. Silakan mulai pasang iklan.");

            return response()->json([
                'success' => true,
                'message' => 'Berhasil approve ' . ($contact->name ?: $contact->phone_number),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal approve: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Contact $contact)
    {
        try {
            $wa = app(WhacenterService::class);
            $status = $contact->onboarding_status ?? '';

            if (str_starts_with($status, 'pending_group_approval:')) {
                $suffix = substr($status, strlen('pending_group_approval:'));
                $parts = explode(':', $suffix, 2);
                $groupJid = $parts[0] ?? '';
                $requesterJid = $parts[1] ?? '';

                if ($groupJid) {
                    $wa->rejectMembership($groupJid, $contact->phone_number, $requesterJid);
                }
            }

            $contact->update([
                'onboarding_status' => 'rejected',
                'is_blocked' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Berhasil reject ' . ($contact->name ?: $contact->phone_number),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal reject: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resendOnboardingAll()
    {
        $pending = Contact::where(function ($q) {
            $q
                ->where('is_registered', false)
                ->orWhereNull('is_registered');
        })
            ->where(function ($q) {
                $q
                    ->whereNull('onboarding_status')
                    ->orWhere('onboarding_status', '!=', 'completed');
            })
            ->where('is_blocked', false)
            ->get();

        $count = $pending->count();
        foreach ($pending as $index => $contact) {
            // Stagger dispatches by 5 seconds per contact to avoid WA rate limits
            SendOnboardingDmJob::dispatch($contact->id)->delay(now()->addSeconds($index * 5));
        }

        return response()->json([
            'success' => true,
            'message' => "Mengirim DM ke {$count} kontak di background (antrian aktif)",
        ]);
    }
}
