<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappGroup;
use App\Services\WhacenterService;

class GroupAdminReplyAgent
{
    public function __construct(
        private WhacenterService $whacenter
    ) {}

    public function handle(Message $message, ?Listing $listing, array $moderation): void
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'GroupAdminReplyAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $message->loadMissing('group');
            $group = $message->group;

            if (!$group || !$group->is_active) {
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'no_active_group']]);
                return;
            }

            // Scenario A: Confirm new listing to group
            if ($listing) {
                $this->sendListingConfirmation($group, $listing, $message);
            }

            // Scenario B: Handle violation
            if ($moderation['is_violation'] ?? false) {
                $this->handleViolation($group, $message, $moderation);
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update(['status' => 'success', 'duration_ms' => $duration]);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function sendListingConfirmation(WhatsappGroup $group, Listing $listing, Message $message): void
    {
        // Group notification is now handled by BroadcastAgent
        // This method only sends DM to seller

        $categoryName = $listing->category?->name ?? 'Umum';
        $priceLabel = $listing->price_formatted;
        $listingUrl = url('/p/' . $listing->id);
        $paddedId = str_pad($listing->id, 5, '0', STR_PAD_LEFT);
        $locationLine = $listing->location ? "📍 Lokasi: {$listing->location}\n" : '';

        // DM to seller with product link (skip if sender is unresolved LID)
        $rawPayload = $message->raw_payload ?? [];
        $senderLid = $rawPayload['sender_lid'] ?? null;
        $isUnresolvedLid = $senderLid && $senderLid === $message->sender_number;
        if ($message->sender_number && !$isUnresolvedLid) {
            $tpl = Setting::get('template_listing_dm', "✅ *Iklan kamu sudah tayang!*\n\n📋 *{title}*\n💰 {priceLabel}\n🔗 {listingUrl}");
            $dmText = str_replace(
                ['{title}', '{categoryName}', '{priceLabel}', '{locationLine}', '{paddedId}', '{listingUrl}'],
                [$listing->title, $categoryName, $priceLabel, $locationLine, $paddedId, $listingUrl],
                $tpl
            );
            $this->whacenter->sendMessage($message->sender_number, $dmText);
        }
    }

    private function handleViolation(WhatsappGroup $group, Message $message, array $moderation): void
    {
        $contact = Contact::where('phone_number', $message->sender_number)->first();
        if (!$contact) {
            return;
        }

        // Refresh to get the latest warning_count (incremented by MessageModerationAgent)
        $contact->refresh();
        $warningCount = $contact->warning_count;

        // Stop sending after 3rd escalation to avoid spam from the bot itself
        if ($warningCount > 3) {
            return;
        }

        // Always delete the offending message from the group
        $this->tryDeleteMessage($message);

        $dmText = $moderation['reply_dm_text'];

        // Send DM to the violator only (no group message)
        // Skip DM if sender is an unresolved LID (not a valid phone number)
        $rawPayload = $message->raw_payload ?? [];
        $senderLid = $rawPayload['sender_lid'] ?? null;
        $isUnresolvedLid = $senderLid && $senderLid === $message->sender_number;
        if ($dmText && !$isUnresolvedLid) {
            $dmFull = "{$dmText}\n\n*Ini adalah peringatan ke-{$warningCount} dari 3.*";
            if ($warningCount >= 3) {
                $dmFull .= "\n\n🚫 Akun kamu telah *diblokir* oleh sistem moderasi karena melanggar aturan sebanyak 3 kali.\nKamu tidak dapat lagi berinteraksi dengan bot marketplace ini.";
            }
            $this->whacenter->sendMessage($message->sender_number, $dmFull);
        }

        // On 3rd warning: block in DB + try to kick from group + escalate to admins
        if ($warningCount >= 3) {
            $contact->update(['is_blocked' => true]);

            // Try to kick the member from the group via Baileys gateway
            $this->tryKickMember($group, $message->sender_number);

            $adminPhones = $this->resolveAdminPhones($group);
            foreach ($adminPhones as $adminPhone) {
                $this->escalateToAdmin($adminPhone, $contact, $message, $moderation, $group);
            }
        }
    }

    private function escalateToAdmin(
        string $adminNumber,
        Contact $contact,
        Message $message,
        array $moderation,
        WhatsappGroup $group
    ): void {
        $senderName = $message->sender_name ?? $message->sender_number;
        $severityLabel = match ($moderation['violation_severity'] ?? 'low') {
            'high' => '🔴 TINGGI',
            'medium' => '🟡 SEDANG',
            default => '🟢 RENDAH',
        };

        $excerpt = mb_substr($message->raw_body ?? '', 0, 200);

        $tpl = Setting::get('template_escalation_report', "🚨 *LAPORAN PELANGGARAN*\n\n👤 Pelanggar: *{senderName}*\n📞 {senderNumber}\n⚠️ {severityLabel}\n📝 {violationReason}\n💬 \"{excerpt}\"");
        $report = str_replace(
            ['{senderName}', '{senderNumber}', '{category}', '{severityLabel}', '{violationReason}', '{totalViolations}', '{datetime}', '{excerpt}'],
            [$senderName, $message->sender_number, ucfirst($moderation['category'] ?? 'unknown'), $severityLabel, $moderation['violation_reason'] ?? '-', $contact->total_violations, now()->format('d/m/Y H:i') . ' WIB', $excerpt],
            $tpl
        );

        $this->whacenter->sendMessage($adminNumber, $report);
    }

    /**
     * Try to delete the offending message from the group via Baileys gateway.
     */
    private function tryDeleteMessage(Message $message): void
    {
        $payload = $message->raw_payload;
        $messageKey = $payload['_key'] ?? null;

        if ($messageKey) {
            $result = $this->whacenter->deleteMessage($messageKey);
        } else {
            $groupJid = $payload['group_id'] ?? $payload['from_group'] ?? null;
            if (!$groupJid || !$message->message_id)
                return;
            $result = $this->whacenter->deleteGroupMessage($groupJid, $message->message_id, $message->sender_number);
        }

        \Illuminate\Support\Facades\Log::info('GroupAdminReplyAgent: tryDeleteMessage', $result ?? []);
    }

    /**
     * Try to kick a member from the group via Baileys gateway.
     */
    private function tryKickMember(WhatsappGroup $group, string $memberNumber): void
    {
        $result = $this->whacenter->kickMember($group->group_id, $memberNumber);
        \Illuminate\Support\Facades\Log::info('GroupAdminReplyAgent: tryKickMember', $result);
    }

    /**
     * Resolve the list of admin phone numbers to notify.
     * Queries all active users with the 'admin' role who have a phone number set.
     * Per-group phone_number is also included if set (and not a duplicate).
     */
    private function resolveAdminPhones(WhatsappGroup $group): array
    {
        $phones = User::role('admin')
            ->where('is_active', true)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->pluck('phone')
            ->toArray();

        // Also include per-group override phone if set
        if ($group->phone_number) {
            $phones[] = $group->phone_number;
        }

        return array_unique($phones);
    }
}
