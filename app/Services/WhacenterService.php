<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhacenterService
{
    private string $gatewayUrl;
    private string $gatewayToken;
    private string $phoneId;

    public function __construct()
    {
        $this->gatewayUrl = rtrim(config('services.wa_gateway.url'), '/');
        $this->gatewayToken = config('services.wa_gateway.token', '');
        $this->phoneId = config('services.wa_gateway.phone_id', '');
    }

    public function sendMessage(string $number, string $message): array
    {
        $result = $this->gatewayPost('/send', [
            'phone_id' => $this->phoneId,
            'number' => $this->normalizeNumber($number),
            'message' => $message,
        ]);
        $outId = $result['data']['result']['data']['id'] ?? null;
        $this->logOutgoing($message, toNumber: $this->normalizeNumber($number), outgoingMessageId: $outId);
        return $result;
    }

    public function sendImageMessage(string $number, string $message, string $imageUrl): array
    {
        $result = $this->gatewayPost('/send-image', [
            'phone_id' => $this->phoneId,
            'number' => $this->normalizeNumber($number),
            'message' => $message,
            'image' => $imageUrl,
        ]);
        $outId = $result['data']['result']['data']['id'] ?? null;
        $this->logOutgoing($message, toNumber: $this->normalizeNumber($number), outgoingMessageId: $outId);
        return $result;
    }

    public function sendGroupMessage(string $groupName, string $message): array
    {
        $result = $this->gatewayPost('/sendGroup', [
            'phone_id' => $this->phoneId,
            'group' => $groupName,
            'message' => $message,
        ]);
        $group = \App\Models\WhatsappGroup::where('group_name', $groupName)->first();
        $outId = $result['data']['result']['data']['id'] ?? null;
        $this->logOutgoing($message, groupId: $group?->id, groupName: $groupName, outgoingMessageId: $outId);
        return $result;
    }

    /**
     * Delete a message for everyone in a group.
     *
     * @param array $messageKey Full Baileys message key: { remoteJid, id, fromMe, participant }
     */
    public function deleteMessage(array $messageKey): array
    {
        return $this->gatewayPost('/delete', ['phone_id' => $this->phoneId, 'key' => $messageKey]);
    }

    /**
     * Delete a message using group_id + message_id + participant fields.
     */
    public function deleteGroupMessage(string $groupId, string $messageId, ?string $participant = null): array
    {
        return $this->gatewayPost('/delete', [
            'phone_id' => $this->phoneId,
            'group_id' => $groupId,
            'message_id' => $messageId,
            'participant' => $participant,
        ]);
    }

    /**
     * Kick a member from a WhatsApp group.
     * The bot must be a group admin.
     */
    public function kickMember(string $groupId, string $memberNumber): array
    {
        return $this->gatewayPost('/kick', [
            'phone_id' => $this->phoneId,
            'group_id' => $groupId,
            'member' => $this->normalizeNumber($memberNumber),
        ]);
    }

    /**
     * Fetch group participants from the WA gateway.
     * Returns array with 'participants' key, each item has 'id' (JID) and 'isAdmin'.
     */
    public function getGroupParticipants(string $groupId): array
    {
        return $this->gatewayGet('/group/participants', ['phone_id' => $this->phoneId, 'group_id' => $groupId]);
    }

    /**
     * Make the bot join a WhatsApp group via invite link.
     * @param string $inviteLinkOrCode Full invite URL (https://chat.whatsapp.com/XXXX) or just the code
     */
    public function joinGroup(string $inviteLinkOrCode): array
    {
        return $this->gatewayPost('/join-group', ['phone_id' => $this->phoneId, 'invite_link' => $inviteLinkOrCode]);
    }

    /**
     * Set group announce mode (only admins can send messages).
     * @param bool $announce true = only admins, false = all members
     */
    public function setGroupAnnounce(string $groupId, bool $announce): array
    {
        return $this->gatewayPost('/group/set-announce', [
            'phone_id' => $this->phoneId,
            'group_id' => $groupId,
            'announce' => $announce,
        ]);
    }

    /**
     * Approve a pending group membership request.
     */
    public function approveMembership(string $groupId, string $requesterPhone, string $requesterJid = ''): array
    {
        $payload = [
            'phone_id' => $this->phoneId,
            'group_id' => $groupId,
            'requester' => preg_replace('/\D/', '', $requesterPhone),
        ];
        if ($requesterJid)
            $payload['requester_jid'] = $requesterJid;
        return $this->gatewayPost('/approve-membership', $payload);
    }

    /**
     * Reject a pending group membership request.
     */
    public function rejectMembership(string $groupId, string $requesterPhone, string $requesterJid = ''): array
    {
        $payload = [
            'phone_id' => $this->phoneId,
            'group_id' => $groupId,
            'requester' => preg_replace('/\D/', '', $requesterPhone),
        ];
        if ($requesterJid)
            $payload['requester_jid'] = $requesterJid;
        return $this->gatewayPost('/reject-membership', $payload);
    }

    public function getProfilePicUrl(string $number): ?string
    {
        $result = $this->gatewayGet('/profile-pic', [
            'phone_id' => $this->phoneId,
            'number' => preg_replace('/\D/', '', $number),
        ]);
        return $result['data']['profile_pic_url'] ?? null;
    }

    public function getBulkProfilePics(array $numbers): array
    {
        $result = $this->gatewayPost('/profile-pics', [
            'phone_id' => $this->phoneId,
            'numbers' => $numbers,
        ]);
        return $result['data']['results'] ?? [];
    }

    // ── Gateway HTTP helpers ──────────────────────────────────────────────

    private function gatewayGet(string $endpoint, array $params = []): array
    {
        try {
            $response = Http::timeout(15)
                ->withToken($this->gatewayToken)
                ->get($this->gatewayUrl . $endpoint, $params);

            $result = $response->json();
            Log::info("WhacenterService::gateway GET {$endpoint}", [
                'status' => $response->status(),
                'result' => $result,
            ]);

            return [
                'success' => $response->successful(),
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::error("WhacenterService::gateway GET {$endpoint} failed", [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function gatewayPost(string $endpoint, array $data): array
    {
        try {
            $response = Http::timeout(15)
                ->withToken($this->gatewayToken)
                ->post($this->gatewayUrl . $endpoint, $data);

            $result = $response->json();
            Log::info("WhacenterService::gateway {$endpoint}", [
                'status' => $response->status(),
                'result' => $result,
            ]);

            return [
                'success' => $response->successful() && ($result['success'] ?? false),
                'data' => $result,
            ];
        } catch (\Exception $e) {
            Log::error("WhacenterService::gateway {$endpoint} failed", [
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function normalizeNumber(string $number): string
    {
        $number = preg_replace('/\D/', '', $number);
        if (str_starts_with($number, '0')) {
            $number = '62' . substr($number, 1);
        }
        return $number;
    }

    private function logOutgoing(string $body, string $toNumber = '', ?int $groupId = null, ?string $groupName = null, ?string $outgoingMessageId = null): void
    {
        try {
            \App\Models\Message::create([
                'whatsapp_group_id' => $groupId,
                'message_id' => $outgoingMessageId,
                'sender_number' => 'bot',
                'sender_name' => 'Jamaah Bot',
                'message_type' => 'text',
                'raw_body' => $body,
                'direction' => 'out',
                'recipient_number' => $toNumber ?: ($groupName ?? null),
                'is_processed' => true,
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('WhacenterService: failed to log outgoing message', ['error' => $e->getMessage()]);
        }
    }
}
