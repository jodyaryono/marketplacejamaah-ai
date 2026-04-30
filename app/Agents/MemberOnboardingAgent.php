<?php

namespace App\Agents;

use App\Jobs\ProcessMessageJob;
use App\Models\AgentLog;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
use App\Models\SystemMessage;
use App\Services\GeminiService;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Log;

class MemberOnboardingAgent
{
    public function __construct(
        private WhacenterService $whacenter,
        private GeminiService $gemini,
    ) {}

    /**
     * Called for GROUP messages.
     * If the sender is not yet fully registered, send them an onboarding DM.
     */
    public function handleGroupMessage(Message $message): void
    {
        // Ensure contact exists (created by WhatsAppListenerAgent before this runs)
        $contact = Contact::firstOrCreate(
            ['phone_number' => $message->sender_number],
            ['name' => $message->sender_name]
        );

        // Already done, or waiting for a reply at any onboarding step — skip
        $activeStatuses = ['pending', 'pending_seller_products', 'pending_buyer_products', 'pending_both_products'];
        if ($contact->is_registered || in_array($contact->onboarding_status, $activeStatuses)) {
            return;
        }

        // Contact is still in the approval pre-screening flow — don't send a second onboarding DM
        if (str_starts_with($contact->onboarding_status ?? '', 'pending_group_approval:')) {
            return;
        }

        $log = AgentLog::create([
            'agent_name' => 'MemberOnboardingAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            // Never greet someone by their phone number
            $rawName = $message->sender_name ?? '';
            $isPhone = $rawName === '' || preg_match('/^\+?[\d\s\-]{7,}$/', $rawName);
            $senderName = $isPhone ? null : $rawName;

            $greeting = $senderName ? "Assalamu'alaikum *{$senderName}*! 🙏" : "Assalamu'alaikum wa rahmatullahi wa barakatuh! 🙏";

            $intro = "{$greeting}\n\n"
                . "✨ *Selamat datang di Marketplace Jamaah!* ✨\n\n"
                . "Satu hal penting yang perlu kamu tahu dulu:\n\n"
                . "🚀 *Cara pasang iklan itu GAMPANG banget!*\n"
                . "Cukup *kirim foto / video + deskripsi + harga* di grup WhatsApp ini — "
                . "iklanmu akan *otomatis muncul di website kami* dalam hitungan detik! 🌐\n\n"
                . "🌐 *marketplacejamaah-ai.jodyaryono.id*\n\n"
                . "Tidak perlu daftar, tidak perlu login, tidak perlu aplikasi lain. "
                . "Cukup chat di grup seperti biasa 😊\n\n"
                . "🕌 *Berkah untuk Masjid*\n"
                . "Setiap transaksi yang berhasil di Marketplace Jamaah, *2,5% otomatis kami sumbangkan ke masjid terdekat* — "
                . "supaya jual-beli kita juga jadi ladang amal jariyah 🤲\n\n"
                . "Nah, supaya aku bisa daftarin kamu resmi sebagai anggota, "
                . "boleh aku kenalan dulu dong? Nama Kakak siapa? 😊";

            $this->whacenter->sendMessage($message->sender_number, $intro);

            // Auto-approve: anggota grup dianggap sah tanpa perlu balas DM dulu.
            // Kalau mereka balas, BotQueryAgent yang handle (bukan onboarding lagi).
            $contact->update(['onboarding_status' => 'completed', 'is_registered' => true]);

            $log->update(['status' => 'success', 'output_payload' => ['action' => 'onboarding_dm_sent']]);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Called for DIRECT MESSAGES when the contact has pending onboarding.
     * Handles both the initial name/role step and the follow-up product step.
     * Returns true if handled as an onboarding reply, false otherwise.
     * Returns false if the contact is registered and we should route to BotQueryAgent.
     */
    public function handleDirectMessage(Message $message): bool
    {
        $contact = Contact::where('phone_number', $message->sender_number)->first();
        if (!$contact) {
            return false;
        }

        // Fully registered contacts → route to BotQueryAgent (not an onboarding reply)
        if ($contact->is_registered && $contact->onboarding_status === 'completed') {
            return false;
        }

        // Handle product info follow-up step
        $productStatuses = ['pending_seller_products', 'pending_buyer_products', 'pending_both_products'];
        if (in_array($contact->onboarding_status, $productStatuses)) {
            return $this->handleProductsReply($message, $contact);
        }

        // Handle group approval pre-screening (user requested to join the group)
        if (str_starts_with($contact->onboarding_status ?? '', 'pending_group_approval:')) {
            return $this->handleGroupApprovalReply($message, $contact);
        }

        // Handle initial name/role step
        if ($contact->onboarding_status !== 'pending') {
            return false;
        }

        $log = AgentLog::create([
            'agent_name' => 'MemberOnboardingAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $replyText = $message->raw_body ?? '';
            $senderName = $contact->getSapaan($message->sender_name);

            // Handle sticker/media without text — describe it for AI context
            if (empty(trim($replyText))) {
                $mediaType = $message->message_type ?? 'unknown';
                $replyText = "[Member mengirim {$mediaType}, tanpa teks]";
            }

            // Build chat history for context
            $chatHistory = $this->buildChatHistory($message->sender_number);

            // Build known data including contact info, listings, and group messages
            $knownStr = $this->buildKnownDataString($contact);

            // Use AI to understand the message AND respond naturally
            $promptTemplate = Setting::get('prompt_onboarding_chat', 'Admin Marketplace Jamaah. Member {senderName} baru gabung. Jawab JSON.');
            $prompt = str_replace(
                ['{senderName}', '{knownStr}', '{chatHistory}', '{replyText}'],
                [$senderName, $knownStr, $chatHistory, $replyText],
                $promptTemplate
            );

            // Ask AI to also infer the appropriate honorific from name/writing style
            $prompt .= "\n\nTambahan wajib: sertakan field \"honorific\" di JSON. Nilai: \"pak\" (pria, paruh baya/tua), \"bu\" (wanita, paruh baya/tua), \"mas\" (pria, muda), \"mbak\" (wanita, muda), \"kak\" (tidak diketahui). Perkirakan dari nama dan cara bicara.";

            $parsed = $this->gemini->generateJson($prompt);

            // AI failed → use a warm generic fallback, but only if we haven't just sent the same message
            if (!$parsed) {
                $fallback = 'Masya Allah, senang sekali bisa kenalan! 😊 Boleh tau nama Kakak siapa dan biasanya jual atau cari produk apa di sini?';
                $lastBotMsg = \App\Models\Message::where('sender_number', 'bot')
                    ->where('recipient_number', $message->sender_number)
                    ->orderBy('created_at', 'desc')
                    ->value('raw_body');
                if ($lastBotMsg !== $fallback) {
                    $this->whacenter->sendMessage($message->sender_number, $fallback);
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'ai_failed']]);
                return true;
            }

            $type = $parsed['type'] ?? 'conversation';

            // Toxic / uncooperative message — stay silent
            if ($type === 'ignore') {
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'ignored_toxic']]);
                return true;
            }

            // Conversational response — AI crafted a natural reply
            if ($type === 'conversation') {
                $reply = $parsed['reply'] ?? '';
                if ($reply && !$this->isDuplicateOfLastBotMessage($message->sender_number, $reply)) {
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'conversation', 'reply' => $reply]]);
                return true;
            }

            // Registration data extracted
            $name = trim($parsed['name'] ?? '') ?: null;
            $kota = trim($parsed['kota'] ?? '') ?: null;
            $role = $parsed['role'] ?? null;

            // Merge with existing contact data — don't lose what we already know
            $name = $name ?: (($contact->name && $contact->name !== $message->sender_number) ? $contact->name : null);
            $kota = $kota ?: ($contact->address ?: null);
            $role = $role ?: ($contact->member_role ?: null);

            // Save partial data incrementally
            $honorific = $parsed['honorific'] ?? null;
            $partialUpdate = [];
            if ($name)
                $partialUpdate['name'] = $name;
            if ($kota)
                $partialUpdate['address'] = $kota;
            if ($role)
                $partialUpdate['member_role'] = $role;
            if ($honorific && in_array($honorific, ['pak', 'bu', 'mas', 'mbak', 'kak']))
                $partialUpdate['honorific'] = $honorific;
            if (!empty($partialUpdate)) {
                $contact->update($partialUpdate);
            }

            if (!($parsed['valid'] ?? false) || !$name || !$role) {
                // Still missing info — AI already included natural follow-up in conversation reply
                $reply = $parsed['reply'] ?? '';
                $sapaan = $contact->fresh()->getSapaan($message->sender_name);
                if (!$reply) {
                    // Fallback: ask for the ONE missing thing naturally
                    if (!$name)
                        $reply = 'Btw, boleh tau namanya siapa nih? 😊';
                    elseif (!$kota)
                        $reply = "Salam kenal *{$sapaan}*! Tinggal di kota mana nih? 😄";
                    else
                        $reply = "Oke *{$sapaan}*! Di grup ini ada jual beli, kamu tertarik jualan, belanja, atau dua-duanya? 😊";
                }
                if (!$this->isDuplicateOfLastBotMessage($message->sender_number, $reply)) {
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'partial_data', 'parsed' => $parsed]]);
                return true;
            }

            $updateData = [
                'onboarding_status' => 'completed',
            ];
            if ($name)
                $updateData['name'] = $name;
            if ($kota)
                $updateData['address'] = $kota;
            if ($role)
                $updateData['member_role'] = $role;
            if ($honorific && in_array($honorific, ['pak', 'bu', 'mas', 'mbak', 'kak']))
                $updateData['honorific'] = $honorific;
            $contact->update($updateData);

            // Natural confirmation
            $roleLabelMap = ['seller' => 'Penjual 🏪', 'buyer' => 'Pembeli 🛍️', 'both' => 'Penjual & Pembeli 🏪🛍️'];
            $roleLabel = $roleLabelMap[$role] ?? 'Anggota';
            $displayName = $contact->fresh()->getSapaan();
            $kotaLine = $kota ? " dari {$kota}" : '';

            $confirm = "Alhamdulillah, *{$displayName}*{$kotaLine} udah terdaftar sebagai {$roleLabel} di Marketplace Jamaah! 🎉\n\n"
                . 'Barakallahu fiik, semoga jadi ladang berkah buat kita semua ya 🙏';

            $this->whacenter->sendMessage($message->sender_number, $confirm);

            // Ask follow-up product question based on role
            $this->askProductQuestion($contact->fresh(), $message->sender_number, $role);

            $log->update([
                'status' => 'success',
                'output_payload' => ['name' => $name, 'kota' => $kota, 'role' => $role],
            ]);

            return true;
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return true;  // still consumed as onboarding reply
        }
    }

    /**
     * Send product question after role is confirmed.
     */
    private function askProductQuestion(Contact $contact, string $phone, ?string $role): void
    {
        $nextStatus = match ($role) {
            'seller' => 'pending_seller_products',
            'buyer' => 'pending_buyer_products',
            'both' => 'pending_both_products',
            default => null,
        };

        if (!$nextStatus) {
            $contact->update(['is_registered' => true, 'onboarding_status' => 'completed']);
            return;
        }

        $contact->update(['is_registered' => false, 'onboarding_status' => $nextStatus]);

        $sapaan = $contact->getSapaan();
        $msg = match ($role) {
            'seller' => "Oh iya *{$sapaan}*, penasaran nih — jualan apa aja di sini? 😊",
            'buyer' => "Btw *{$sapaan}*, lagi nyari produk apa nih di marketplace? Siapa tau aku bisa bantu cariin 😊",
            default => "Oh iya *{$sapaan}*, ceritain dong — jualan apa dan lagi nyari produk apa? 😊",
        };
        $this->whacenter->sendMessage($phone, $msg);
    }

    /**
     * Handle the product info reply step.
     */
    private function handleProductsReply(Message $message, Contact $contact): bool
    {
        $log = AgentLog::create([
            'agent_name' => 'MemberOnboardingAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $replyText = trim($message->raw_body ?? '');
            $senderName = $contact->getSapaan();
            $status = $contact->onboarding_status;

            if (empty($replyText)) {
                $mediaType = $message->message_type ?? 'unknown';
                $replyText = "[Member mengirim {$mediaType}, tanpa teks]";
            }

            $chatHistory = $this->buildChatHistory($message->sender_number);

            $roleContext = match ($status) {
                'pending_seller_products' => 'mau jualan',
                'pending_buyer_products' => 'mau belanja',
                'pending_both_products' => 'mau jualan dan belanja',
                default => '',
            };

            $promptTemplate = Setting::get('prompt_onboarding_products', 'Admin Marketplace Jamaah. {senderName} bilang {roleContext}. Tanya produk. Jawab JSON.');
            $prompt = str_replace(
                ['{senderName}', '{roleContext}', '{chatHistory}', '{replyText}'],
                [$senderName, $roleContext, $chatHistory, $replyText],
                $promptTemplate
            );

            $parsed = $this->gemini->generateJson($prompt);

            if (!$parsed || ($parsed['type'] ?? '') === 'conversation') {
                $reply = $parsed['reply'] ?? "Boleh cerita dong *{$senderName}*, {$roleContext} apa nih? 😊";
                if (!$this->isDuplicateOfLastBotMessage($message->sender_number, $reply)) {
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'conversation', 'parsed' => $parsed]]);
                return true;
            }

            // Products extracted
            $updateData = [
                'is_registered' => true,
                'onboarding_status' => 'completed',
            ];

            $sellProducts = trim($parsed['sell_products'] ?? '');
            $buyProducts = trim($parsed['buy_products'] ?? '');

            if ($sellProducts)
                $updateData['sell_products'] = $sellProducts;
            if ($buyProducts)
                $updateData['buy_products'] = $buyProducts;

            $contact->update($updateData);

            $this->reprocessPendingMessages($contact);

            // Natural completion reply from AI
            $reply = $parsed['reply'] ?? "Mantap *{$senderName}*! Udah terdaftar lengkap di Marketplace Jamaah 🎉 Barakallahu fiik!";
            $this->whacenter->sendMessage($message->sender_number, $reply);

            // Send guide as separate message
            $guide = "🎉 *Kamu Resmi Terdaftar di Marketplace Jamaah!*\n\n"
                . "📣 *CARA PASANG IKLAN — Sangat Mudah!*\n"
                . "Cukup kirim pesan di *grup WhatsApp* ini dengan format:\n"
                . "• Foto/video produk (opsional tapi bikin cepat laku 📸)\n"
                . "• Nama & deskripsi produk\n"
                . "• Harga\n\n"
                . "⚡ Iklanmu akan *otomatis tampil di website* dalam hitungan detik!\n"
                . "Tidak perlu login. Tidak perlu aplikasi lain. Cukup kirim di grup!\n\n"
                . "🌐 Lihat semua iklan di:\n"
                . "*marketplacejamaah-ai.jodyaryono.id*\n\n"
                . "🔍 *Fitur lain:*\n"
                . "• Ketik _cari [produk]_ di chat ini → aku carikan di marketplace\n"
                . "• Ketik _edit #nomor_ → edit iklan langsung dari chat WhatsApp\n"
                . "• Ketik _bantuan_ → lihat semua perintah yang tersedia\n\n"
                . "🕌 *Berkah untuk Masjid*\n"
                . "Setiap transaksi sukses di Marketplace Jamaah, *2,5% otomatis kami sumbangkan ke masjid terdekat* — "
                . "jualanmu sekaligus jadi ladang amal jariyah 🤲\n\n"
                . 'Semoga berkah dan lancar jualannya ya! 🙏';
            $this->whacenter->sendMessage($message->sender_number, $guide);

            $log->update(['status' => 'success', 'output_payload' => ['sell' => $sellProducts, 'buy' => $buyProducts]]);
            return true;
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return true;
        }
    }

    /**
     * Build recent chat history between bot and a member for AI context.
     */
    private function buildChatHistory(string $phoneNumber, int $limit = 10): string
    {
        // Get recent DM messages (both incoming from member and outgoing from bot)
        $messages = Message::where(function ($q) use ($phoneNumber) {
            $q
                ->where('sender_number', $phoneNumber)
                ->whereNull('whatsapp_group_id');
        })
            ->orWhere(function ($q) use ($phoneNumber) {
                $q
                    ->where('sender_number', 'bot')
                    ->where('recipient_number', $phoneNumber);
            })
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return '(belum ada percakapan sebelumnya)';
        }

        $lines = [];
        foreach ($messages as $msg) {
            $who = $msg->sender_number === 'bot' ? 'ADMIN' : 'MEMBER';
            $body = $msg->raw_body ?? "[{$msg->message_type}]";
            // Truncate long messages
            if (mb_strlen($body) > 200) {
                $body = mb_substr($body, 0, 200) . '...';
            }
            $lines[] = "{$who}: {$body}";
        }

        return implode("\n", $lines);
    }

    /**
     * Build a comprehensive "known data" string for AI context.
     * Includes contact info, listings posted, and recent group messages.
     */
    private function buildKnownDataString(Contact $contact): string
    {
        $parts = [];

        // 1. Contact profile data
        if ($contact->name && $contact->name !== $contact->phone_number) {
            $parts[] = "nama: {$contact->name}";
        }
        if ($contact->honorific) {
            $parts[] = "panggilan: {$contact->getHonorific()}";
        }
        if ($contact->address) {
            $parts[] = "kota/domisili: {$contact->address}";
        }
        if ($contact->member_role) {
            $roleLabels = ['seller' => 'penjual', 'buyer' => 'pembeli', 'both' => 'penjual & pembeli'];
            $parts[] = "role: " . ($roleLabels[$contact->member_role] ?? $contact->member_role);
        }
        if ($contact->sell_products) {
            $parts[] = "produk dijual: {$contact->sell_products}";
        }
        if ($contact->buy_products) {
            $parts[] = "produk dicari: {$contact->buy_products}";
        }

        // 2. Listings this contact has posted
        $listings = Listing::where('contact_id', $contact->id)
            ->whereIn('status', ['active', 'pending'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['title', 'price_label', 'location', 'status']);

        if ($listings->isNotEmpty()) {
            $listingLines = $listings->map(function ($l) {
                $info = $l->title;
                if ($l->price_label) $info .= " ({$l->price_label})";
                if ($l->location) $info .= " - {$l->location}";
                return "  • {$info}";
            })->join("\n");
            $parts[] = "iklan yang pernah diposting:\n{$listingLines}";
        }

        // 3. Recent group messages from this sender (to infer intent)
        $groupMsgs = Message::where('sender_number', $contact->phone_number)
            ->whereNotNull('whatsapp_group_id')
            ->whereNotNull('raw_body')
            ->where('raw_body', '!=', '')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['raw_body']);

        if ($groupMsgs->isNotEmpty()) {
            $msgLines = $groupMsgs->map(fn($m) => '  • ' . mb_substr($m->raw_body, 0, 150))->join("\n");
            $parts[] = "pesan di grup:\n{$msgLines}";
        }

        if (empty($parts)) {
            return '';
        }

        return "\nDATA YANG SUDAH DIKETAHUI TENTANG MEMBER INI:\n" . implode("\n", $parts)
            . "\n\nGUNAKAN data di atas! JANGAN tanyakan ulang info yang sudah ada. Jika dari data sudah jelas role-nya (misal sudah posting iklan = seller), langsung pakai.";
    }

    /**
     * Re-queue group messages that were held with message_category='pending_onboarding'
     * so they can be classified as ads now that the user has completed registration.
     */
    private function reprocessPendingMessages(Contact $contact): void
    {
        $messages = Message::where('sender_number', $contact->phone_number)
            ->where('message_category', 'pending_onboarding')
            ->whereNotNull('whatsapp_group_id')  // GROUP messages only, not DMs
            ->get();

        foreach ($messages as $msg) {
            $msg->update([
                'is_processed' => false,
                'message_category' => null,
                'processed_at' => null,
            ]);
            ProcessMessageJob::dispatch($msg->id)->onQueue('agents');
        }

        if ($messages->count() > 0) {
            Log::info("MemberOnboardingAgent: re-queued {$messages->count()} pending_onboarding messages for {$contact->phone_number}");
        }
    }

    /**
     * Handle DM reply from someone who submitted a group join request.
     * Parses name, kota, and role via AI, then approves/rejects via gateway.
     */
    private function handleGroupApprovalReply(Message $message, Contact $contact): bool
    {
        // Status format: pending_group_approval:{groupJid} (old)
        //             or: pending_group_approval:{groupJid}:{requesterJid} (new)
        $statusSuffix = substr($contact->onboarding_status, strlen('pending_group_approval:'));
        $colonPos = strpos($statusSuffix, ':');
        if ($colonPos !== false) {
            $groupJid = substr($statusSuffix, 0, $colonPos);
            $requesterJid = substr($statusSuffix, $colonPos + 1);
        } else {
            $groupJid = $statusSuffix;
            $requesterJid = '';
        }

        $log = AgentLog::create([
            'agent_name' => 'MemberOnboardingAgent',
            'message_id' => $message->id,
            'status' => 'processing',
            'input_payload' => ['flow' => 'group_approval', 'phone' => $contact->phone_number, 'group' => $groupJid],
        ]);

        try {
            $replyText = trim($message->raw_body ?? '');
            $senderName = $contact->getSapaan($message->sender_name);
            $groupName = \App\Models\WhatsappGroup::where('group_id', $groupJid)->value('group_name') ?? 'Marketplace Jamaah';

            if (empty($replyText)) {
                $this->whacenter->sendMessage($message->sender_number, 'Halo! Pesannya kosong nih 😅 Bisa bales lagi? Kasih tau nama, kota, dan mau jualan atau belanja ya 🙏');
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'empty_reply']]);
                return true;
            }

            // Build chat history and known data
            $chatHistory = $this->buildChatHistory($message->sender_number);

            $knownStr = $this->buildKnownDataString($contact);

            $promptTemplate = Setting::get('prompt_onboarding_approval', 'Admin {groupName}. {senderName} mau gabung. Jawab JSON.');
            $prompt = str_replace(
                ['{groupName}', '{senderName}', '{replyText}', '{knownStr}', '{chatHistory}'],
                [$groupName, $senderName, $replyText, $knownStr, $chatHistory],
                $promptTemplate
            );

            $parsed = $this->gemini->generateJson($prompt);

            if (!$parsed || ($parsed['type'] ?? '') === 'conversation') {
                $reply = $parsed['reply'] ?? "Maaf ya *{$senderName}*, bisa kasih tau nama, kota, dan mau jualan/belanja? Contoh: _\"{$senderName}, Jakarta, mau jualan\"_ 🙏";
                if (!$this->isDuplicateOfLastBotMessage($message->sender_number, $reply)) {
                    $this->whacenter->sendMessage($message->sender_number, $reply);
                }
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'conversation_or_failed', 'parsed' => $parsed]]);
                return true;
            }

            $name = trim($parsed['name'] ?? '') ?: null;
            $kota = trim($parsed['kota'] ?? '') ?: null;
            $role = $parsed['role'] ?? null;

            // Merge with existing contact data — don't lose what we already know
            $name = $name ?: (($contact->name && $contact->name !== $message->sender_number) ? $contact->name : null);
            $kota = $kota ?: ($contact->address ?: null);
            $role = $role ?: ($contact->member_role ?: null);

            // Save partial data incrementally so next message remembers
            $partialUpdate = [];
            if ($name && $name !== $contact->name)
                $partialUpdate['name'] = $name;
            if ($kota && $kota !== $contact->address)
                $partialUpdate['address'] = $kota;
            if ($role && $role !== $contact->member_role)
                $partialUpdate['member_role'] = $role;
            if (!empty($partialUpdate)) {
                $contact->update($partialUpdate);
            }

            // Check if registration is complete (name + role required at minimum)
            if (!($parsed['valid'] ?? false) || !$name || !$role) {
                $reply = $parsed['reply'] ?? '';
                if (!$reply) {
                    if (!$name)
                        $reply = 'Btw, boleh tau namanya siapa nih? 😊';
                    elseif (!$kota)
                        $reply = "Salam kenal *{$name}*! Tinggal di kota mana nih? 😄";
                    else
                        $reply = "Oke *{$name}*! Di grup ini ada jual beli, kamu tertarik jualan, belanja, atau dua-duanya? 😊";
                }
                $this->whacenter->sendMessage($message->sender_number, $reply);
                $log->update(['status' => 'skipped', 'output_payload' => ['reason' => 'partial_data', 'parsed' => $parsed]]);
                return true;
            }

            // Approve the membership request via gateway (pass requesterJid for @lid accounts)
            $approved = false;
            try {
                $result = app(\App\Services\WhacenterService::class)->approveMembership($groupJid, $contact->phone_number, $requesterJid);
                $approved = $result['success'] ?? false;
                Log::info('MemberOnboardingAgent: approveMembership result', ['result' => $result, 'phone' => $contact->phone_number, 'jid' => $requesterJid]);
            } catch (\Exception $e) {
                // 404 or similar means the member is already in the group — treat as approved
                $errorMsg = $e->getMessage();
                Log::warning('MemberOnboardingAgent: approveMembership API failed', ['error' => $errorMsg]);
                if (str_contains($errorMsg, '404') || str_contains($errorMsg, 'not found') || str_contains($errorMsg, 'already')) {
                    $approved = true;
                    Log::info("MemberOnboardingAgent: treating as approved (member likely already in group)");
                }
            }

            // If approved: complete onboarding. If not: save data but keep pending_group_approval
            // status so group_join event can finish the flow when admin approves.
            if ($approved) {
                $updateData = ['onboarding_status' => 'completed', 'is_registered' => true];
            } else {
                // Keep pending_group_approval:... status — will be resolved when group_join fires
                $updateData = [];
            }
            if ($name)
                $updateData['name'] = $name;
            if ($kota)
                $updateData['address'] = $kota;
            if ($role)
                $updateData['member_role'] = $role;
            if (!empty($updateData)) {
                $contact->update($updateData);
            }

            $roleLabels = ['seller' => 'Penjual 🏪', 'buyer' => 'Pembeli 🛍️', 'both' => 'Penjual & Pembeli 🏪🛍️'];
            $roleLabel = $roleLabels[$role] ?? ($role ?? '-');
            $displayName = $name ?? $message->sender_number;
            $groupName = \App\Models\WhatsappGroup::where('group_id', $groupJid)->value('group_name') ?? 'Marketplace Jamaah';
            $kotaLine = $kota ? "\n📍 Kota: {$kota}" : '';

            if ($approved) {
                $confirmMsg = SystemMessage::getText('group_approval.approved', [
                    'name' => $displayName,
                    'group_name' => $groupName,
                    'kota' => $kota ?? '-',
                    'role_label' => $roleLabel,
                ], "✅ *Permintaan Bergabung Disetujui!*\n\nHalo *{$displayName}*! 🎉\n\nKamu sudah kami setujui bergabung ke grup *{$groupName}*. Selamat datang! 🙏");
            } else {
                $confirmMsg = SystemMessage::getText('group_approval.processing', [
                    'name' => $displayName,
                    'group_name' => $groupName,
                    'kota_line' => $kota ? " | Kota: {$kota}" : '',
                    'role_label' => $roleLabel,
                ], "✅ *Data diterima!*\n\nHalo *{$displayName}*! 👋\n\nData kamu sudah kami catat. Permintaan bergabung ke grup *{$groupName}* sedang diproses admin. 🙏");
            }

            $this->whacenter->sendMessage($message->sender_number, $confirmMsg);

            $log->update([
                'status' => 'success',
                'output_payload' => ['name' => $name, 'kota' => $kota, 'role' => $role, 'approved' => $approved, 'group_jid' => $groupJid],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('MemberOnboardingAgent::handleGroupApprovalReply failed', ['error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return true;
        }
    }

    /**
     * Admin-triggered: resend onboarding DM to a pending contact.
     * For contacts with NULL status: sends the initial welcome DM.
     * For contacts with 'pending' status: sends a follow-up reminder.
     * For contacts in product-question stage: re-asks the product question.
     */
    public function resendOnboarding(Contact $contact): void
    {
        $log = AgentLog::create([
            'agent_name' => 'MemberOnboardingAgent',
            'status' => 'processing',
            'input_payload' => ['action' => 'resend_onboarding', 'contact_id' => $contact->id, 'current_status' => $contact->onboarding_status],
        ]);

        try {
            // Never use raw phone number as a greeting name
            $rawName = $contact->name ?? '';
            $isPhone = $rawName === '' || preg_match('/^\+?[\d\s\-]{7,}$/', $rawName);
            $senderName = $isPhone ? 'Akhi/Ukhti' : $rawName;
            $safeDisplayName = $isPhone ? null : $rawName;
            $status = $contact->onboarding_status;

            if (in_array($status, ['pending_seller_products', 'pending_buyer_products', 'pending_both_products'])) {
                // Re-ask product question
                $role = match ($status) {
                    'pending_seller_products' => 'seller',
                    'pending_buyer_products' => 'buyer',
                    'pending_both_products' => 'both',
                };
                $this->askProductQuestion($contact, $contact->phone_number, $role);
            } elseif ($status === 'pending') {
                // Already got initial DM but never replied — send reminder
                $nameOrAkhi = $safeDisplayName ? "*{$safeDisplayName}*" : 'Akhi/Ukhti';
                $reminder = "Assalamu'alaikum {$nameOrAkhi}! 👋\n\n"
                    . 'Kami dari *Admin Marketplace Jamaah* masih menunggu perkenalan kamu nih. '
                    . "Cukup balas nama, domisili, dan mau jualan/belanja/keduanya ya!\n\n"
                    . 'Contoh: _"Budi Santoso, Tangerang Selatan, mau jualan hijab"_ 🙏';
                $this->whacenter->sendMessage($contact->phone_number, $reminder);
            } else {
                // NULL status or other — send initial welcome DM, bot introduces itself first
                $greeting = $safeDisplayName ? "Assalamu'alaikum *{$safeDisplayName}*! 🙏" : "Assalamu'alaikum wa rahmatullahi wa barakatuh! 🙏";
                $fallback = "{$greeting}\n\n"
                    . 'Perkenalkan, saya *Admin Marketplace Jamaah* — komunitas jual beli sesama Muslim yang amanah. '
                    . "Senang ada anggota baru bergabung! 😊\n\n"
                    . 'Supaya bisa aktif di grup, boleh kenalan dulu? '
                    . 'Balas nama kamu, tinggal di kota mana, dan mau jualan, belanja, atau keduanya ya 🙏';
                $intro = SystemMessage::getText('onboarding.welcome', ['name' => $safeDisplayName ?? ''], $fallback);
                // Safety net: if DB template still contains raw phone number, use fallback
                if ($safeDisplayName === null && str_contains($intro, $contact->phone_number)) {
                    $intro = $fallback;
                }
                $this->whacenter->sendMessage($contact->phone_number, $intro);
                $contact->update(['onboarding_status' => 'pending']);
            }

            $log->update(['status' => 'success', 'output_payload' => ['action' => 'resend_' . ($status ?? 'initial')]]);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Prevent the bot from sending the exact same question/reply it sent last.
     * Protects against loops where the AI can't extract new data from a confused
     * reply and keeps asking the same thing.
     */
    private function isDuplicateOfLastBotMessage(string $phone, string $reply): bool
    {
        $normalize = fn(string $s) => trim(preg_replace('/\s+/u', ' ', $s));
        $target = $normalize($reply);
        if ($target === '') {
            return false;
        }

        $lastBotMsg = Message::where('sender_number', 'bot')
            ->where('recipient_number', $phone)
            ->orderByDesc('created_at')
            ->value('raw_body');

        if (!$lastBotMsg) {
            return false;
        }

        return $normalize($lastBotMsg) === $target;
    }

    private function fallbackParse(string $text): array
    {
        $name = null;
        $role = null;

        // Try "Nama | Role" format
        if (str_contains($text, '|')) {
            [$namePart, $rolePart] = array_map('trim', explode('|', $text, 2));
            $name = $namePart ?: null;
            $text = $rolePart;
        }

        $upper = strtoupper($text);
        if (str_contains($upper, 'KEDUANYA') || str_contains($upper, 'BOTH') || str_contains($upper, 'DUA')) {
            $role = 'both';
        } elseif (str_contains($upper, 'PENJUAL') || str_contains($upper, 'JUAL') || str_contains($upper, 'SELLER')) {
            $role = 'seller';
        } elseif (str_contains($upper, 'PEMBELI') || str_contains($upper, 'BELI') || str_contains($upper, 'BUYER')) {
            $role = 'buyer';
        }

        return ['name' => $name, 'role' => $role];
    }

    /**
     * Detect if the user is confused, asking for help, or sending something
     * completely unrelated to the onboarding registration.
     */
    private function isConfusedOrUnrelated(string $text): bool
    {
        $lower = strtolower(trim($text));

        // Confused / doesn't understand
        if (preg_match('/\b(ga|gak?|nggak?|tidak|tdk|gk|g)\s*(ngerti|paham|faham|mudeng|mengerti|tau|tahu)\b/ui', $lower)) {
            return true;
        }

        // Asking what this is / what to do
        if (preg_match('/\b(ini apa|apaan|maksudnya|gimana|bagaimana|caranya|apa ini|ngapain|bingung|confused)\b/ui', $lower)) {
            return true;
        }

        // Apologizing / saying it's wrong / complaining
        if (preg_match('/\b(maaf|sorry|salah|keliru)\b/ui', $lower) && !preg_match('/\b(jual|beli|seller|buyer|penjual|pembeli)\b/ui', $lower)) {
            return true;
        }

        // Greetings with no registration info (just "assalamualaikum" / "halo" alone)
        if (preg_match('/^(assalamu.?alaikum|halo|hai|hi|hey|salam|permisi|om|kak|bang|mas|mba|mbak)\s*[!.?,]*$/ui', $lower)) {
            return true;
        }

        // User says they're just sharing info, not trying to register
        if (preg_match('/\b(cuma|hanya|sekedar)\s*(info|infokan|share|berbagi|ngasih|kasih)\b/ui', $lower)) {
            return true;
        }

        // "bantuan" / "help" / "menu"
        if (preg_match('/\b(bantuan|help|menu|tolong|bantu)\b/ui', $lower)) {
            return true;
        }

        return false;
    }
}
