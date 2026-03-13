<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Contact;
use App\Models\Message;
use App\Models\Setting;
use App\Models\SystemMessage;
use App\Models\WhatsappGroup;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppListenerAgent
{
    public function handle(array $payload): ?Message
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'WhatsAppListenerAgent',
            'input_payload' => $payload,
            'status' => 'processing',
        ]);

        try {
            // Normalize payload from whacenter.com webhook
            $groupId = $payload['group_id'] ?? $payload['from_group'] ?? null;
            $messageId = $payload['message_id'] ?? $payload['id'] ?? null;
            $senderNum = $payload['sender'] ?? $payload['from'] ?? '';
            // Strip WhatsApp JID suffixes (@c.us, @lid, @s.whatsapp.net, etc.)
            $senderNum = preg_replace('/@\S+/', '', $senderNum);
            // Normalize Indonesian numbers: 08XX → 628XX
            if (preg_match('/^0\d{8,12}$/', $senderNum)) {
                $senderNum = '62' . substr($senderNum, 1);
            }

            // Skip WhatsApp status broadcasts — not real group/DM messages
            if (
                str_contains($senderNum, 'status@') ||
                str_contains((string) ($groupId ?? ''), 'status@') ||
                str_contains((string) ($messageId ?? ''), 'status@broadcast')
            ) {
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'status_broadcast']]);
                return null;
            }
            $senderName = $payload['sender_name'] ?? $payload['pushname'] ?? null;

            // Skip forwarded bot notifications — when a group member forwards the bot's own
            // confirmation message back into the group, it must not be re-processed as a new ad.
            $rawBody = $payload['message'] ?? $payload['text'] ?? null;
            $isForwarded = !empty($payload['isForwarded']);
            if ($isForwarded && $rawBody && $groupId) {
                $botSignatures = [
                    '✅ *Iklan Diterima!*',
                    '✅ *Iklan kamu sudah tayang!*',
                    '📢 *Iklan Baru!*',
                    '🔄 *Iklan Lama Dihapus & Diganti*',
                    '*Iklan Kamu (', // "Iklan Kamu (2 terakhir)" listing summary
                    '📋 *Iklan Kamu',
                    'Selamat datang di Marketplace Jamaah',
                    '*Peringatan dari Admin MarketplaceJamaah*',
                    'Mau edit? Ketik edit #',
                    '*Koreksi Info Sebelumnya*',
                    'ID Iklan: #',
                ];
                foreach ($botSignatures as $sig) {
                    if (str_contains($rawBody, $sig)) {
                        $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'forwarded_bot_notification']]);
                        return null;
                    }
                }
            }

            // Normalize WhaCentre type: 'conversation' is a plain text message
            $rawType = $payload['type'] ?? 'text';
            $messageType = match (true) {
                $rawType === 'conversation' => 'text',
                in_array($rawType, ['location', 'locationMessage']) => 'other',
                default => $rawType,
            };
            $mediaUrl = $payload['media_url'] ?? $payload['url'] ?? null;

            // If gateway sent base64 media_data (no public URL), save to storage
            $mediaData = $payload['media_data'] ?? null;
            $mediaMimetype = $payload['media_mimetype'] ?? 'image/jpeg';
            if ($mediaData && !$mediaUrl) {
                try {
                    $ext = match(true) {
                        str_contains($mediaMimetype, 'png')  => 'png',
                        str_contains($mediaMimetype, 'gif')  => 'gif',
                        str_contains($mediaMimetype, 'webp') => 'webp',
                        str_contains($mediaMimetype, 'mp4')  => 'mp4',
                        str_contains($mediaMimetype, 'pdf')  => 'pdf',
                        default => 'jpg',
                    };
                    $filename = 'messages/' . uniqid('wa_') . '.' . $ext;
                    $decoded = base64_decode($mediaData);
                    $written = Storage::disk('public')->put($filename, $decoded);
                    if ($written && Storage::disk('public')->exists($filename)) {
                        $mediaUrl = Storage::disk('public')->url($filename);
                    } else {
                        Log::warning('WhatsAppListenerAgent: media file write failed or not found after put', ['filename' => $filename]);
                    }
                } catch (\Exception $e) {
                    Log::warning('WhatsAppListenerAgent: media save failed', ['error' => $e->getMessage()]);
                }
            }
            // Strip bulky base64 media_data from payload before storing in DB
            unset($payload['media_data']);

            $sentAt = isset($payload['timestamp'])
                ? \Carbon\Carbon::createFromTimestamp($payload['timestamp'])
                : now();

            // Find or create group
            $group = null;
            if ($groupId) {
                // Try to match an existing manually-added group by name first
                $groupName = $payload['group_name'] ?? null;
                $group = WhatsappGroup::where('group_id', $groupId)->first();

                if (!$group && $groupName) {
                    // Link manual entry: match by trimmed name (handles extra spaces)
                    $manual = WhatsappGroup::whereRaw('TRIM(group_name) = ?', [trim($groupName)])
                        ->where('group_id', 'like', 'manual-%')
                        ->first();
                    if ($manual) {
                        $manual->update(['group_id' => $groupId]);
                        $group = $manual->fresh();
                    }
                }

                if (!$group) {
                    $group = WhatsappGroup::create([
                        'group_id' => $groupId,
                        'group_name' => $groupName ?? $groupId,
                        'is_active' => true,
                    ]);
                }

                $group->increment('message_count');
                $group->update(['last_message_at' => $sentAt]);
            }

            // Auto-correct LID contacts: when gateway resolved a real phone from a LID,
            // it sends sender_lid = the old LID number. Update any stale contact that has
            // phone_number = LID so it now stores the real phone number instead.
            $senderLid = $payload['sender_lid'] ?? null;
            if ($senderLid && $senderLid !== $senderNum) {
                $lidContact = Contact::where('phone_number', $senderLid)->first();
                if ($lidContact && !Contact::where('phone_number', $senderNum)->exists()) {
                    $lidContact->update(['phone_number' => $senderNum]);
                    // Also fix any listings that were created with the LID contact
                    \App\Models\Listing::where('contact_id', $lidContact->id)
                        ->whereNull('contact_number')
                        ->update(['contact_number' => $senderNum]);
                    // Fix messages with LID sender_number
                    Message::where('sender_number', $senderLid)
                        ->update(['sender_number' => $senderNum]);
                    Log::info("WhatsAppListenerAgent: LID contact corrected {$senderLid} → {$senderNum} (+ listings/messages)");
                } elseif ($lidContact) {
                    // LID contact exists but real phone contact also exists — merge
                    $realContact = Contact::where('phone_number', $senderNum)->first();
                    if ($realContact) {
                        // Move listings from LID contact to real contact
                        \App\Models\Listing::where('contact_id', $lidContact->id)
                            ->update(['contact_id' => $realContact->id, 'contact_number' => $senderNum]);
                        Message::where('sender_number', $senderLid)
                            ->update(['sender_number' => $senderNum]);
                        $realContact->increment('message_count', $lidContact->message_count);
                        $realContact->increment('ad_count', $lidContact->ad_count);
                        $lidContact->delete();
                        Log::info("WhatsAppListenerAgent: LID contact {$senderLid} merged into {$senderNum}");
                    }
                }
            }

            // Unresolved LID: gateway couldn't resolve LID → phone, or from/@lid without sender_lid.
            // Try to find the real phone from a previously resolved message.
            $fromJid = $payload['from'] ?? $payload['_key']['remoteJid'] ?? '';
            $isUnresolvedLid = ($senderLid && $senderLid === $senderNum)
                || (!$senderLid && str_contains($fromJid, '@lid'));
            if ($isUnresolvedLid) {
                $realPhone = Message::whereRaw("raw_payload->>'sender_lid' = ?", [$senderNum])
                    ->where('sender_number', '!=', $senderNum)
                    ->value('sender_number');
                if ($realPhone) {
                    // Merge LID contact into real contact if both exist
                    $lidContact = Contact::where('phone_number', $senderNum)->first();
                    $realContact = Contact::where('phone_number', $realPhone)->first();
                    if ($lidContact && $realContact) {
                        // Move listings from LID contact to real contact
                        \App\Models\Listing::where('contact_id', $lidContact->id)
                            ->update(['contact_id' => $realContact->id, 'contact_number' => $realPhone]);
                        Message::where('sender_number', $senderNum)->update(['sender_number' => $realPhone]);
                        $realContact->increment('message_count', $lidContact->message_count);
                        $realContact->increment('ad_count', $lidContact->ad_count);
                        $lidContact->delete();
                    } elseif ($lidContact) {
                        $lidContact->update(['phone_number' => $realPhone]);
                        \App\Models\Listing::where('contact_id', $lidContact->id)
                            ->whereNull('contact_number')
                            ->update(['contact_number' => $realPhone]);
                    }
                    $senderNum = $realPhone;
                    $isUnresolvedLid = false;
                    Log::info("WhatsAppListenerAgent: unresolved LID mapped to {$realPhone}");
                } else {
                    Log::warning("WhatsAppListenerAgent: LID {$senderNum} could not be resolved to a real phone number");
                }
            }

            // Find or create contact
            $contact = Contact::firstOrCreate(
                ['phone_number' => $senderNum],
                ['name' => $senderName]
            );
            $contact->increment('message_count');

            // Only overwrite the stored name with the WA pushname if the contact
            // hasn't yet provided their real name through onboarding.
            // Once they've replied to the initial step (name+role), their onboarding_status
            // advances to a 'pending_*_products' state — at that point the name is already
            // set to their real name (e.g. "Suryo") and must NOT be overwritten by the WA
            // pushname (e.g. "syn") even though is_registered is still false.
            $nameIsLocked = $contact->is_registered ||
                in_array($contact->onboarding_status, [
                    'pending_seller_products',
                    'pending_buyer_products',
                    'pending_both_products',
                    'completed',
                ]);
            $nameUpdate = ['last_seen' => $sentAt];
            if (!$nameIsLocked) {
                $nameUpdate['name'] = $senderName ?? $contact->name;
            }
            $contact->update($nameUpdate);

            // Avoid duplicate messages by message_id (webhook replay protection)
            if ($messageId && Message::where('message_id', $messageId)->exists()) {
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'duplicate_message_id']]);
                return null;
            }

            // Save the message to DB first so it always appears in the dashboard,
            // even if it gets deleted from the group by one of the guards below.
            try {
                $message = Message::create([
                    'whatsapp_group_id' => $group?->id,
                    'message_id' => $messageId,
                    'sender_number' => $senderNum,
                    'sender_name' => $senderName,
                    'message_type' => in_array($messageType, ['text', 'image', 'document', 'video', 'audio', 'sticker']) ? $messageType : 'other',
                    'raw_body' => $rawBody,
                    'media_url' => $mediaUrl,
                    'is_processed' => false,
                    'sent_at' => $sentAt,
                    'raw_payload' => $payload,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'duplicate_race_condition']]);
                return null;
            }

            // Status-mention guard: when a member mentions the group in their
            // WhatsApp status, it appears as a group message with null/empty body,
            // type "conversation", and no media.  Auto-delete these immediately.
            if ($group && !$rawBody && !$mediaUrl && $messageType === 'text') {
                try {
                    $wa = app(WhacenterService::class);
                    $msgKey = $payload['_key'] ?? null;
                    if ($msgKey) {
                        $wa->deleteMessage($msgKey);
                    } elseif ($messageId && $groupId) {
                        $wa->deleteGroupMessage($groupId, $messageId, $senderNum);
                    }
                } catch (\Exception $e) {
                    Log::warning('WhatsAppListenerAgent: status-mention delete failed', ['error' => $e->getMessage()]);
                }
                $message->update(['is_processed' => true, 'message_category' => 'status_mention_deleted']);
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'status_mention', 'sender' => $senderNum]]);
                return null;
            }

            // One-liner guard: plain text only — never delete media messages (image/video/audio/document/sticker)
            // even if the caption is short. Only delete genuinely text-only one-liners.
            $isMediaMessage = in_array($messageType, ['image', 'video', 'audio', 'sticker', 'document'])
                || ($payload['media_data'] ?? null);
            if ($group && $rawBody && !$mediaUrl && !$isMediaMessage) {
                $trimmed = trim($rawBody);
                $lineCount = substr_count($trimmed, "\n") + 1;
                $wordCount = str_word_count($trimmed);
                // Price pattern: digits followed by k/rb/ribu/jt/juta/miliar, or "Rp" with digits, or a 4+ digit number (price value)
                $hasPrice = (bool) preg_match('/\d+\s*[kK]\b|\d+[.,]?\d*\s*(rb|ribu|jt|juta|miliar|miliyar|milyar)\b|Rp\.?\s*\d|\b\d{4,}\b/i', $trimmed);
                if ($lineCount === 1 && $wordCount < 12 && !$hasPrice) {
                    try {
                        $wa = app(WhacenterService::class);
                        // 1. Delete the message from the group
                        $msgKey = $payload['_key'] ?? null;
                        if ($msgKey) {
                            $wa->deleteMessage($msgKey);
                        } elseif ($messageId) {
                            $wa->deleteGroupMessage($groupId, $messageId, $senderNum);
                        }
                        // 2. DM the sender with a warning (skip if sender is unresolved LID)
                        if (!$isUnresolvedLid) {
                            $dmPayload = SystemMessage::getPayload('group.oneliner_warning', [
                                'name' => $senderName ?? $senderNum,
                                'group_name' => $group->group_name,
                                'phone' => $senderNum,
                            ], "Halo *{$senderName}*! 🙏 Pesan kamu di *{$group->group_name}* aku hapus dulu ya — kurang kelihatan jualannya. Kalau mau posting iklan, tulis lebih lengkap sedikit — nama barang, harga, sama cara hubungi kamu aja cukup kok 😊");
                            if (!empty($dmPayload['media_url'])) {
                                $wa->sendImageMessage($senderNum, $dmPayload['text'], $dmPayload['media_url']);
                            } else {
                                $wa->sendMessage($senderNum, $dmPayload['text']);
                            }
                        } else {
                            Log::warning("WhatsAppListenerAgent: skipping one-liner DM — sender is unresolved LID {$senderNum}");
                        }
                    } catch (\Exception $e) {
                        Log::warning('WhatsAppListenerAgent: one-liner action failed', ['error' => $e->getMessage()]);
                    }
                    $message->update(['is_processed' => true]);
                    $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'one_liner', 'body' => $trimmed]]);
                    return null;
                }
            }

            // Content-based dedup: same sender, same group, same body within 24 hours
            // Prevents a member from re-posting the same ad → delete duplicate + DM with link
            if ($rawBody && $group) {
                $contentHash = md5($rawBody);
                $originalMessage = Message::where('sender_number', $senderNum)
                    ->where('whatsapp_group_id', $group->id)
                    ->whereRaw('md5(raw_body) = ?', [$contentHash])
                    ->where('id', '!=', $message->id)
                    ->where('created_at', '>=', now()->subDay())
                    ->first();
                if ($originalMessage) {
                    try {
                        $wa = app(WhacenterService::class);
                        // 1. Delete the re-post from the group (keep group clean)
                        $msgKey = $payload['_key'] ?? null;
                        if ($msgKey) {
                            $wa->deleteMessage($msgKey);
                        } elseif ($messageId && $groupId) {
                            $wa->deleteGroupMessage($groupId, $messageId, $senderNum);
                        }
                        // 2. Find the existing listing and REFRESH it (update source_date to now)
                        // so it moves to the top of the marketplace feed — the re-post is treated
                        // as a "refresh" intent, not a violation.
                        $listing = \App\Models\Listing::where('message_id', $originalMessage->id)->first();
                        $baseUrl = rtrim(config('app.url'), '/');
                        $name = $senderName ?? $senderNum;
                        if ($listing) {
                            $listing->update(['source_date' => now()]);
                            $listingUrl = "{$baseUrl}/p/{$listing->id}";
                            $tpl = Setting::get('template_duplicate_with_listing', "Halo *{name}*! 🙏\n\nEh, kamu tadi kirim ulang iklan yang sama 😊 Duplikatnya aku hapus dari grup biar rapi, tapi tenang — iklanmu di marketplace sudah aku *refresh* biar balik ke urutan atas.\n\n📦 *{title}*\n🔗 {listingUrl}");
                            $dm = str_replace(['{name}', '{title}', '{listingUrl}'], [$name, $listing->title, $listingUrl], $tpl);
                        } else {
                            $tpl = Setting::get('template_duplicate_no_listing', "Halo *{name}*! 🙏\n\nKamu tadi kirim pesan yang sama seperti sebelumnya 😊 Aku hapus duplikatnya biar grupnya tetap rapi ya. _Kalau iklanmu belum muncul di website, tunggu bentar atau kabarin aku._");
                            $dm = str_replace('{name}', $name, $tpl);
                        }
                        if (!$isUnresolvedLid) {
                            $wa->sendMessage($senderNum, $dm);
                        } else {
                            Log::warning("WhatsAppListenerAgent: skipping duplicate DM — sender is unresolved LID {$senderNum}");
                        }
                    } catch (\Exception $e) {
                        Log::warning('WhatsAppListenerAgent: duplicate delete/DM failed', ['error' => $e->getMessage()]);
                    }
                    $message->update(['is_processed' => true]);
                    $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'duplicate_content_refreshed', 'sender' => $senderNum]]);
                    return null;
                }
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update([
                'message_id' => $message->id,
                'status' => 'success',
                'output_payload' => ['message_id' => $message->id],
                'duration_ms' => $duration,
            ]);

            return $message;
        } catch (\Exception $e) {
            Log::error('WhatsAppListenerAgent failed', ['error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return null;
        }
    }
}
