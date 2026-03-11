<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\WhatsappGroup;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request)
    {
        $query = Message::with(['group', 'senderContact', 'recipientContact'])->latest();

        if ($request->filled('group_id')) {
            $query->where('whatsapp_group_id', $request->group_id);
        }
        if ($request->filled('type')) {
            $query->where('message_type', $request->type);
        }
        if ($request->filled('is_ad')) {
            $query->where('is_ad', $request->is_ad === '1');
        }
        if ($request->filled('search')) {
            $query->where('raw_body', 'ilike', '%' . $request->search . '%');
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        $messages = $query->paginate(25)->withQueryString();
        $groups = WhatsappGroup::where('is_active', true)->get();

        $stats = [
            'total' => Message::count(),
            'today' => Message::whereDate('created_at', today())->count(),
            'ads' => Message::where('is_ad', true)->count(),
            'unprocessed' => Message::where('is_processed', false)->where('direction', 'in')->count(),
            'outgoing' => Message::where('direction', 'out')->whereDate('created_at', today())->count(),
        ];

        return view('messages.index', compact('messages', 'groups', 'stats'));
    }

    public function show(Message $message)
    {
        $message->load(['group', 'listing.category']);
        $agentLogs = $message->agentLogs()->latest()->get();
        return view('messages.show', compact('message', 'agentLogs'));
    }

    public function sendDm(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'message' => 'required|string|max:4000',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        if (empty($phone)) {
            return back()->with('error', 'Nomor tidak valid.');
        }

        try {
            app(\App\Services\WhacenterService::class)->sendMessage($phone, $request->message);
            return back()->with('success', "Pesan berhasil dikirim ke {$phone}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal kirim: ' . $e->getMessage());
        }
    }
}
