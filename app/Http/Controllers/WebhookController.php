<?php

namespace App\Http\Controllers;

use App\Agents\WhatsAppListenerAgent;
use App\Jobs\ProcessMessageJob;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\SystemMessage;
use App\Models\WhatsappGroup;
use App\Services\WhacenterService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function receive(Request $request, WhatsAppListenerAgent $listener): Response
    {
        // Support both JSON and form-encoded payloads (WhaCentre sends JSON)
        $payload = $request->isJson() ? $request->json()->all() : $request->all();

        Log::info('Webhook received', ['payload' => $payload]);

        try {
            if (empty($payload)) {
                return response('Bad Request', 400);
            }

            // Detect group participant leave/remove events before regular message processing
            if ($this->isGroupLeaveEvent($payload)) {
                $this->handleMemberLeft($payload);
                return response('OK', 200);
            }

            // Handle WA message reaction (emoji like/heart/etc)
            if (($payload['type'] ?? '') === 'reaction') {
                $this->handleReaction($payload);
                return response('OK', 200);
            }

            // Handle group membership/join request — AI pre-screens and approves
            if (($payload['type'] ?? '') === 'group_membership_request') {
                $this->handleGroupMembershipRequest($payload);
                return response('OK', 200);
            }

            // Handle member directly added to group by admin (no prior join-request)
            if ($this->isGroupJoinEvent($payload)) {
                $this->handleMemberAdded($payload);
                return response('OK', 200);
            }

            $message = $listener->handle($payload);

            if ($message) {
                ProcessMessageJob::dispatch($message->id)->onQueue('agents');
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Webhook error', ['error' => $e->getMessage()]);
            return response('Error', 500);
        }
    }

    /**
     * Detect WhaCentre group participant leave or remove events.
     * WhaCentre may send these with various payload shapes.
     */
    private function handleGroupMembershipRequest(array $payload): void
    {
        $requesterPhone = preg_replace('/@\S+/', '', $payload['requester'] ?? '');
        if (empty($requesterPhone)) {
            Log::warning('WebhookController: group_membership_request with no requester', $payload);
            return;
        }
        // Normalize Indonesian number
        if (preg_match('/^0\d{8,12}$/', $requesterPhone)) {
            $requesterPhone = '62' . substr($requesterPhone, 1);
        }
        // If requester is a LID number (14+ digits, not starting with 62), we cannot onboard them via DM.
        // Auto-approve immediately so they can join the group.
        $requesterJid = $payload['requester_jid'] ?? '';
        if (preg_match('/^\d{14,}$/', $requesterPhone) && !str_starts_with($requesterPhone, '62')) {
            Log::info("WebhookController: LID membership request from {$requesterPhone}, auto-approving");
            $groupId = $payload['group_id'] ?? null;
            if ($groupId) {
                try {
                    app(WhacenterService::class)->approveMembership($groupId, $requesterPhone, $requesterJid);
                } catch (\Exception $e) {
                    Log::warning('WebhookController: LID auto-approve failed', ['error' => $e->getMessage()]);
                }
            }
            return;
        }
        // Store the original requester_jid for use when calling approve/reject.
        $requesterJid = $payload['requester_jid'] ?? '';

        $groupId = $payload['group_id'] ?? null;
        $groupName = $payload['group_name'] ?? 'Marketplace Jamaah';

        if (!$groupId) {
            Log::warning('WebhookController: group_membership_request missing group_id', $payload);
            return;
        }

        Log::info("WebhookController: join request from {$requesterPhone} (jid: {$requesterJid}) for group {$groupName}");

        try {
            $contact = Contact::firstOrCreate(
                ['phone_number' => $requesterPhone],
                ['name' => null]
            );

            // Status format: pending_group_approval:{groupJid}:{requesterJid}
            // requesterJid stored so approve/reject uses the correct JID (handles @lid accounts)
            if (!str_starts_with($contact->onboarding_status ?? '', 'pending_group_approval:')) {
                $statusValue = $requesterJid
                    ? "pending_group_approval:{$groupId}:{$requesterJid}"
                    : "pending_group_approval:{$groupId}";
                $contact->update(['onboarding_status' => $statusValue]);
            }

            $wa = app(WhacenterService::class);
            $defaultAsk = "Halo! 👋\n\nAda permintaan bergabung dari kamu ke grup *{$groupName}*. 🕌\n\nSebelum kami setujui, boleh kenalan dulu dong? 😊\n\nCukup balas dengan perkenalan singkat, misalnya:\n_\"Jody Aryono, dari Tangerang Selatan, mau jualan dan beli\"_\n\nTidak perlu kaku, yang penting ada nama, domisili, dan kamu mau jual, beli, atau keduanya ya! 🙏";
            $askMsg = SystemMessage::getText('group_approval.ask_data', ['group_name' => $groupName], $defaultAsk);
            $wa->sendMessage($requesterPhone, $askMsg);
        } catch (\Exception $e) {
            Log::error('WebhookController::handleGroupMembershipRequest failed', ['error' => $e->getMessage()]);
        }
    }

    private function handleReaction(array $payload): void
    {
        try {
            $senderNum = preg_replace('/@\S+/', '', $payload['sender'] ?? '');
            // Normalize Indonesian number
            if (preg_match('/^0\d{8,12}$/', $senderNum)) {
                $senderNum = '62' . substr($senderNum, 1);
            }
            if (empty($senderNum) || (preg_match('/^\d{14,}$/', $senderNum) && !str_starts_with($senderNum, '62')))
                return;

            $targetMsgId = $payload['target_message_id'] ?? null;
            $emoji = $payload['emoji'] ?? null;
            $groupId = $payload['group_id'] ?? $payload['from_group'] ?? null;
            $senderName = $payload['sender_name'] ?? $payload['pushname'] ?? null;
            $sentAt = isset($payload['timestamp'])
                ? \Carbon\Carbon::createFromTimestamp($payload['timestamp'])
                : now();

            // Try to resolve the reacted-to message
            $message = $targetMsgId ? Message::where('message_id', $targetMsgId)->first() : null;

            // Resolve group
            $group = null;
            if ($groupId) {
                $group = WhatsappGroup::where('group_id', $groupId)->first();
            }

            // Upsert reaction: one emoji per sender per target message
            MessageReaction::updateOrCreate(
                [
                    'target_message_id' => $targetMsgId,
                    'sender_number' => $senderNum,
                ],
                [
                    'message_id' => $message?->id,
                    'sender_name' => $senderName,
                    'emoji' => $emoji,  // null = reaction removed
                    'whatsapp_group_id' => $group?->id,
                    'raw_payload' => $payload,
                    'reacted_at' => $sentAt,
                ]
            );

            Log::info('Reaction recorded', [
                'sender' => $senderNum,
                'emoji' => $emoji,
                'target' => $targetMsgId,
            ]);
        } catch (\Exception $e) {
            Log::error('WebhookController::handleReaction failed', ['error' => $e->getMessage()]);
        }
    }

    private function isGroupJoinEvent(array $payload): bool
    {
        $type = $payload['type'] ?? '';
        $event = $payload['event'] ?? '';
        $action = $payload['action'] ?? '';

        // Format 1: { type: "group_participants_update", action: "add" }
        if ($type === 'group_participants_update' && $action === 'add') {
            return true;
        }
        // Format 2: { event: "participant.added" } or { event: "participant.joined" }
        if ($event === 'participant.added' || $event === 'participant.joined') {
            return true;
        }
        // Format 3: { type: "notify", action: "add", group_id: ... }
        if ($type === 'notify' && $action === 'add' && !empty($payload['group_id'])) {
            return true;
        }
        return false;
    }

    /**
     * Handle a member being directly added to the group by an admin.
     * Sends an onboarding DM if the member hasn't been onboarded yet.
     */
    private function handleMemberAdded(array $payload): void
    {
        Log::info('WebhookController: group_add event received', $payload);

        $rawParticipants = $payload['participants'] ?? ($payload['participant'] ?? null);
        if (empty($rawParticipants)) {
            Log::warning('WebhookController::handleMemberAdded: no participants in payload', $payload);
            return;
        }

        $participants = is_array($rawParticipants) ? $rawParticipants : [$rawParticipants];
        $participantDetails = collect($payload['participant_details'] ?? [])
            ->keyBy('phone');
        $wa = app(WhacenterService::class);

        foreach ($participants as $raw) {
            $phone = preg_replace('/@\S+/', '', (string) $raw);
            if (empty($phone)) {
                continue;
            }

            // Normalize Indonesian number
            if (preg_match('/^0\d{8,12}$/', $phone)) {
                $phone = '62' . substr($phone, 1);
            }

            // Skip LID numbers — internal WA IDs (14+ digits, never start with 62)
            if (preg_match('/^\d{14,}$/', $phone) && !str_starts_with($phone, '62')) {
                Log::info("WebhookController::handleMemberAdded: skipping LID number {$phone}");
                continue;
            }

            // Get pushname from participant_details (gateway resolves per-participant)
            $detail = $participantDetails->get($phone);
            $pushname = $detail['name'] ?? $payload['sender_name'] ?? $payload['pushname'] ?? null;

            $contact = Contact::firstOrCreate(
                ['phone_number' => $phone],
                ['name' => $pushname]
            );

            // If contact was in the group-approval pre-screening flow and now group_join fires,
            // it means the admin already approved them.
            if (str_starts_with($contact->onboarding_status ?? '', 'pending_group_approval:')) {
                Log::info("WebhookController::handleMemberAdded: {$phone} was pending_group_approval, admin approved");

                // If approval pre-screening already collected name & role, complete onboarding directly
                if ($contact->name && $contact->member_role) {
                    Log::info("WebhookController::handleMemberAdded: {$phone} data already collected, completing onboarding");
                    $contact->update(['onboarding_status' => 'completed', 'is_registered' => true]);

                    $roleLabels = ['seller' => 'Penjual 🏪', 'buyer' => 'Pembeli 🛍️', 'both' => 'Penjual & Pembeli 🏪🛍️'];
                    $roleLabel = $roleLabels[$contact->member_role] ?? $contact->member_role;
                    $kotaLine = $contact->address ? "\n📍 Kota: {$contact->address}" : '';
                    $welcomeMsg = "✅ *Selamat datang di Marketplace Jamaah!* 🎉\n\n"
                        . "Halo *{$contact->name}*! Admin sudah menyetujui permintaan bergabungmu. 🙏\n\n"
                        . "📝 Nama: {$contact->name}{$kotaLine}\n"
                        . "🏷️ Peran: {$roleLabel}\n\n"
                        . "Kamu sudah resmi jadi anggota! Silakan langsung posting iklanmu di grup:\n"
                        . "📸 Kirim *foto + deskripsi + harga* di grup — iklanmu otomatis muncul di website! 🌐\n\n"
                        . "🌐 *marketplacejamaah-ai.jodyaryono.id*\n\n"
                        . "Selamat berjualan & berbelanja! Barakallahu fiikum 🤝";
                    try {
                        $wa->sendMessage($phone, $welcomeMsg);
                    } catch (\Exception $e) {
                        Log::error("WebhookController::handleMemberAdded: failed to send welcome to {$phone}", ['error' => $e->getMessage()]);
                    }
                    continue;
                }

                // Data not yet collected — clear status and proceed with normal onboarding DM below
                Log::info("WebhookController::handleMemberAdded: {$phone} no data yet, proceeding with onboarding DM");
                $contact->update(['onboarding_status' => null]);
            }

            // Skip if already fully onboarded or mid-onboarding
            $activeStatuses = ['pending', 'pending_seller_products', 'pending_buyer_products', 'pending_both_products', 'completed'];
            if (
                $contact->is_registered ||
                in_array($contact->onboarding_status, $activeStatuses)
            ) {
                Log::info("WebhookController::handleMemberAdded: {$phone} already onboarded/active, skipping");
                continue;
            }

            Log::info("WebhookController::handleMemberAdded: sending onboarding DM to {$phone}");

            try {
                // Never greet someone by their raw phone number
                $rawName = $pushname ?? '';
                $isPhone = $rawName === '' || preg_match('/^\+?[\d\s\-]{7,}$/', $rawName);
                $senderName = $isPhone ? null : $rawName;

                $greeting = $senderName
                    ? "Assalamu'alaikum *{$senderName}*! 🙏"
                    : "Assalamu'alaikum wa rahmatullahi wa barakatuh! 🙏";

                $intro = "{$greeting}\n\n"
                    . 'Perkenalkan, saya *Admin Marketplace Jamaah* — komunitas jual beli sesama Muslim yang amanah dan berkah. '
                    . "Alhamdulillah senang ada anggota baru, semoga jadi ladang berkah ya 😊\n\n"
                    . 'Eh, saya belum kenal nih — boleh tau nama Kakak siapa? 😊';

                $wa->sendMessage($phone, $intro);
                $contact->update(['onboarding_status' => 'pending']);
            } catch (\Exception $e) {
                Log::error("WebhookController::handleMemberAdded: failed to send DM to {$phone}", ['error' => $e->getMessage()]);
            }
        }
    }

    private function isGroupLeaveEvent(array $payload): bool
    {
        $type = $payload['type'] ?? '';
        $event = $payload['event'] ?? '';
        $action = $payload['action'] ?? '';

        // Format 1: { type: "group_participants_update", action: "leave"|"remove" }
        if ($type === 'group_participants_update' && in_array($action, ['leave', 'remove'])) {
            return true;
        }
        // Format 2: { event: "participant.left" } or { event: "participant.removed" }
        if (str_starts_with($event, 'participant.')) {
            return true;
        }
        // Format 3: { type: "notify", action: "leave"|"remove", group_id: ... }
        if ($type === 'notify' && in_array($action, ['leave', 'remove']) && !empty($payload['group_id'])) {
            return true;
        }
        return false;
    }

    /**
     * Handle a member leaving or being removed from the group:
     * - Mark their listings as expired
     * - Update contact status to not registered
     * - Send them a farewell DM explaining that their listings were removed
     */
    private function handleMemberLeft(array $payload): void
    {
        // Extract participant phone(s) — can be array or single string
        $rawParticipants = $payload['participants'] ?? ($payload['participant'] ?? null);
        if (empty($rawParticipants)) {
            Log::warning('WebhookController::handleMemberLeft: no participants in payload', $payload);
            return;
        }

        $participants = is_array($rawParticipants) ? $rawParticipants : [$rawParticipants];

        $groupName = $payload['group_name'] ?? 'Marketplace Jamaah';
        $wa = app(WhacenterService::class);

        foreach ($participants as $raw) {
            // Strip WhatsApp JID suffixes
            $phone = preg_replace('/@\S+/', '', (string) $raw);
            if (empty($phone)) {
                continue;
            }

            // Expire all listings for this contact
            $deleted = Listing::where(function ($q) use ($phone) {
                $q
                    ->where('contact_number', $phone)
                    ->orWhereHas('contact', fn($c) => $c->where('phone_number', $phone));
            })
                ->where('status', '!=', 'expired')
                ->count();

            Listing::where(function ($q) use ($phone) {
                $q
                    ->where('contact_number', $phone)
                    ->orWhereHas('contact', fn($c) => $c->where('phone_number', $phone));
            })
                ->where('status', '!=', 'expired')
                ->update(['status' => 'expired']);

            // Update contact registration status
            Contact::where('phone_number', $phone)->update([
                'is_registered' => false,
                'onboarding_status' => null,
            ]);

            Log::info("WebhookController: member {$phone} left group, {$deleted} listings expired");

            // Farewell DM
            if ($deleted > 0) {
                $dm = "Halo! 👋\n\nKamu telah keluar dari grup *{$groupName}*.\n\n"
                    . "Sebagai informasi, *{$deleted} iklan* kamu di Marketplace Jamaah telah otomatis dihapus dari database.\n\n"
                    . "Jika ingin berjualan kembali, bergabunglah kembali ke grup dan daftarkan dirimu. Semua iklan sebelumnya perlu dikirim ulang.\n\n"
                    . '_Terima kasih sudah pernah bergabung! Semoga sukses._ 🙏';
            } else {
                $dm = "Halo! 👋\n\nKamu telah keluar dari grup *{$groupName}*.\n\n"
                    . "Jika ingin bergabung kembali dan berjualan, silahkan kembali bergabung ke grup.\n\n"
                    . '_Terima kasih! Semoga sukses._ 🙏';
            }

            try {
                $wa->sendMessage($phone, $dm);
            } catch (\Exception $e) {
                Log::warning("WebhookController: failed to send farewell DM to {$phone}", ['error' => $e->getMessage()]);
            }
        }
    }
}
