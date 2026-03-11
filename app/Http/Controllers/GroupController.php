<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\WhatsappGroup;
use App\Services\WhacenterService;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function index()
    {
        $groups = WhatsappGroup::withCount('messages')
            ->addSelect([
                'unique_senders' => Message::selectRaw('COUNT(DISTINCT sender_number)')
                    ->whereColumn('whatsapp_group_id', 'whatsapp_groups.id'),
            ])
            ->latest()
            ->paginate(15);

        $stats = [
            'total' => WhatsappGroup::count(),
            'active' => WhatsappGroup::where('is_active', true)->count(),
            'total_messages' => \App\Models\Message::count(),
            'total_ads' => \App\Models\Message::where('is_ad', true)->count(),
        ];

        return view('groups.index', compact('groups', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'group_name' => 'required|string|max:255|unique:whatsapp_groups,group_name',
            'description' => 'nullable|string',
            'phone_number' => 'nullable|string',
        ]);

        // Auto-generate a unique placeholder group_id.
        // Will be replaced automatically when real WA messages arrive.
        $data['group_id'] = 'manual-' . \Illuminate\Support\Str::slug($data['group_name'], '-') . '-' . uniqid();

        WhatsappGroup::create($data);
        return back()->with('success', 'Grup "' . e($data['group_name']) . '" berhasil ditambahkan.');
    }

    public function update(Request $request, WhatsappGroup $group)
    {
        $data = $request->validate([
            'group_name' => 'required|string|max:255',
            'group_id' => 'nullable|string|unique:whatsapp_groups,group_id,' . $group->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Don't overwrite group_id with null — keep existing if not provided
        if (empty($data['group_id'])) {
            unset($data['group_id']);
        }

        $group->update($data);
        return back()->with('success', 'Grup "' . $group->fresh()->group_name . '" berhasil diperbarui.');
    }

    public function destroy(WhatsappGroup $group)
    {
        $name = $group->group_name;
        $group->delete();
        return back()->with('success', 'Grup "' . $name . '" berhasil dihapus.');
    }

    /**
     * Return JSON of members (from DB messages) and admins (from participants_raw).
     */
    public function participants(WhatsappGroup $group)
    {
        $members = \App\Models\Contact::whereHas('messages', fn($q) =>
            $q->where('whatsapp_group_id', $group->id))->get(['id', 'name', 'phone_number', 'is_registered', 'member_role', 'sell_products', 'buy_products']);

        $admins = [];
        if (!empty($group->participants_raw)) {
            $roleLabel = ['seller' => 'Penjual', 'buyer' => 'Pembeli', 'both' => 'Penjual & Pembeli'];
            $admins = collect($group->participants_raw)
                ->filter(fn($p) => ($p['isAdmin'] ?? false) || ($p['isSuperAdmin'] ?? false))
                ->map(function ($p) use ($roleLabel) {
                    $phone = preg_replace('/[^0-9]/', '', explode('@', $p['id'] ?? '')[0] ?? '');
                    $contact = \App\Models\Contact::where('phone_number', $phone)->first();
                    return [
                        'phone' => $phone,
                        'name' => $contact?->name ?? 'N/A',
                        'is_registered' => (bool) ($contact?->is_registered),
                        'role' => $roleLabel[$contact?->member_role ?? ''] ?? '-',
                    ];
                })
                ->values();
        }

        return response()->json([
            'group' => $group->group_name,
            'members' => $members,
            'admins' => $admins,
            'member_count' => $members->count(),
            'admin_count' => count($admins),
        ]);
    }

    /**
     * Sync group participants from WA gateway API.
     */
    public function sync(WhatsappGroup $group)
    {
        if (str_starts_with($group->group_id, 'manual-')) {
            return back()->with('error', 'Grup ini belum terhubung ke WhatsApp. Tunggu pesan pertama masuk dari grup tersebut.');
        }

        $result = app(WhacenterService::class)->getGroupParticipants($group->group_id);

        if ($result['success'] && !empty($result['data'])) {
            $raw = $result['data']['participants'] ?? $result['data'] ?? [];
            $admins = collect($raw)->filter(fn($p) => ($p['isAdmin'] ?? false) || ($p['isSuperAdmin'] ?? false))->count();

            $group->update([
                'participants_raw' => $raw,
                'admin_count' => $admins,
            ]);

            return back()->with('success', 'Sinkronisasi berhasil. ' . count($raw) . ' anggota ditemukan, ' . $admins . ' admin.');
        }

        return back()->with('error', 'Gagal sinkronisasi. Pastikan bot sudah bergabung ke grup dan group_id sudah benar.');
    }

    /**
     * Send a broadcast message to a WhatsApp group.
     */
    public function sendMessage(Request $request, WhatsappGroup $group)
    {
        $data = $request->validate([
            'message' => 'required|string|max:4096',
        ]);

        if (str_starts_with($group->group_id, 'manual-')) {
            return back()->with('error', 'Grup ini belum terhubung ke WhatsApp.');
        }

        $result = app(WhacenterService::class)->sendGroupMessage($group->group_id, $data['message']);

        if ($result['success']) {
            return back()->with('success', 'Pesan berhasil dikirim ke grup "' . $group->group_name . '".');
        }

        $errMsg = $result['data']['error'] ?? ($result['error'] ?? 'Unknown error');
        return back()->with('error', 'Gagal mengirim pesan: ' . $errMsg);
    }

    /**
     * Toggle "only admins can send message" mode for a WhatsApp group.
     */
    public function announce(WhatsappGroup $group)
    {
        if (str_starts_with($group->group_id, 'manual-')) {
            return back()->with('error', 'Grup ini belum terhubung ke WhatsApp. Tunggu pesan pertama masuk dari grup tersebut.');
        }

        $newState = !$group->only_admins_can_send;

        $result = app(WhacenterService::class)->setGroupAnnounce($group->group_id, $newState);

        if ($result['success']) {
            $group->update(['only_admins_can_send' => $newState]);
            $msg = $newState
                ? 'Mode pengumuman aktif — hanya admin yang bisa kirim pesan.'
                : 'Mode pengumuman nonaktif — semua anggota bisa kirim pesan.';
            return back()->with('success', $msg);
        }

        $errMsg = $result['data']['error'] ?? ($result['error'] ?? 'Unknown error');
        return back()->with('error', 'Gagal mengubah pengaturan grup: ' . $errMsg);
    }
}
