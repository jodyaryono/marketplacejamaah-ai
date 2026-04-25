<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
use App\Models\WhatsappGroup;
use App\Services\GeminiService;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * MasterCommandAgent — handles commands from the master/owner number.
 * Any DM from MASTER_PHONE is processed here before all other pipelines.
 * Responds in Bahasa Indonesia, executes requested actions, reports status.
 */
class MasterCommandAgent
{
    public function __construct(
        private WhacenterService $whacenter,
        private GeminiService $gemini,
    ) {}

    /**
     * Check if this message is from master.
     */
    public static function isMaster(Message $message): bool
    {
        return self::isMasterPhone($message->sender_number);
    }

    /**
     * Check if a phone number belongs to master.
     */
    public static function isMasterPhone(string $phone): bool
    {
        $masterPhone = config('services.wa_gateway.master_phone', '');
        return $masterPhone !== '' && $phone === $masterPhone;
    }

    /**
     * Handle master command. Returns true always (message fully consumed).
     */
    public function handle(Message $message): bool
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'MasterCommandAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            $command = trim($message->raw_body ?? '');

            if ($command === '') {
                $this->reply('Halo Master! 👋 Silakan ketik perintah yang ingin dijalankan.');
                $log->update(['status' => 'skipped']);
                return true;
            }

            $result = $this->parseAndExecute($command, $message);

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update([
                'status' => 'success',
                'output_payload' => $result,
                'duration_ms' => $duration,
            ]);
        } catch (\Exception $e) {
            Log::error('MasterCommandAgent failed', ['error' => $e->getMessage()]);
            $this->reply("❌ Terjadi error:\n" . $e->getMessage());
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Command parsing via Gemini
    // ─────────────────────────────────────────────────────────────────────────

    private function parseAndExecute(string $command, Message $message): array
    {
        // Fast-path keyword routing — bypass AI for unambiguous bulk commands.
        $lower = strtolower($command);
        if (preg_match('/\bapprove\b.*\b(semua|all|pending)\b/u', $lower)) {
            return $this->execApproveAllPending();
        }

        $promptTemplate = Setting::get('prompt_master_command', 'Kamu asisten marketplace. Perintah: {command}. Jawab JSON action.');
        $prompt = str_replace('{command}', $command, $promptTemplate);

        $parsed = $this->gemini->generateJson($prompt) ?? ['action' => 'unknown'];
        $action = $parsed['action'] ?? 'unknown';

        return match ($action) {
            'system_health_report' => $this->execSystemHealthReport(),
            'health_check' => $this->execHealthCheck(),
            'approve_all_pending' => $this->execApproveAllPending(),
            'send_dm' => $this->execSendDm($parsed),
            'send_group' => $this->execSendGroup($parsed),
            'ban_user' => $this->execBanUser($parsed),
            'unban_user' => $this->execUnbanUser($parsed),
            'delete_listing' => $this->execDeleteListing($parsed),
            'kick_member' => $this->execKickMember($parsed),
            'broadcast' => $this->execBroadcast($parsed),
            'status' => $this->execStatus(),
            'help' => $this->execHelp(),
            default => $this->execUnknown($command),
        };
    }

    private function execApproveAllPending(): array
    {
        // Approve dua kategori sekaligus:
        //  1. pending   — sudah dapat DM tapi belum balas
        //  2. stuck     — onboarding_status NULL & belum registered (tidak pernah dapat DM)
        // Kebijakan: anggota grup auto-valid, no manual approval.
        $affected = Contact::where('is_blocked', false)
            ->where(function ($q) {
                $q->where('onboarding_status', 'pending')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('onboarding_status')->where('is_registered', false);
                  });
            })
            ->update([
                'onboarding_status' => 'completed',
                'is_registered' => true,
            ]);

        if ($affected === 0) {
            $this->reply("✅ Tidak ada kontak yang perlu di-approve. Semua sudah bersih! 🎉");
        } else {
            $this->reply("✅ Berhasil approve *{$affected} kontak* (termasuk pending & stuck).\n\nMereka sekarang resmi terdaftar tanpa perlu balas DM perkenalan.");
        }

        return ['approved_count' => $affected];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Action executors
    // ─────────────────────────────────────────────────────────────────────────

    private function execSendDm(array $p): array
    {
        $phone = $this->normalizePhone($p['phone'] ?? '');
        $text = $p['message'] ?? '';

        if (!$phone || !$text) {
            $this->reply('⚠️ Tidak bisa kirim DM — nomor HP atau pesan kosong.');
            return ['error' => 'missing_params'];
        }

        $this->whacenter->sendMessage($phone, $text);
        $this->reply("✅ Pesan berhasil dikirim ke *{$phone}*.");
        return ['sent_to' => $phone];
    }

    private function execSendGroup(array $p): array
    {
        $groupName = $p['group_name'] ?? '';
        $text = $p['message'] ?? '';

        if (!$groupName || !$text) {
            // Fallback: cari grup pertama yang aktif
            $group = WhatsappGroup::where('is_active', true)->first();
            if (!$group) {
                $this->reply('⚠️ Tidak ditemukan grup aktif.');
                return ['error' => 'no_group'];
            }
            $groupName = $group->group_name;
        }

        $this->whacenter->sendGroupMessage($groupName, $text);
        $this->reply("✅ Pesan berhasil dikirim ke grup *{$groupName}*.");
        return ['sent_to_group' => $groupName];
    }

    private function execBanUser(array $p): array
    {
        $phone = $this->normalizePhone($p['phone'] ?? '');
        if (!$phone) {
            $this->reply('⚠️ Nomor HP tidak ditemukan dalam perintah.');
            return ['error' => 'no_phone'];
        }

        $contact = Contact::where('phone_number', $phone)->first();
        if (!$contact) {
            $this->reply("⚠️ Kontak *{$phone}* tidak ditemukan di database.");
            return ['error' => 'not_found'];
        }

        $contact->update(['is_blocked' => true]);
        $name = $contact->name ?? $phone;
        $this->reply("🚫 *{$name}* ({$phone}) berhasil diblokir.");

        // Optionally notify the contact
        $reason = $p['reason'] ?? 'Melanggar aturan marketplace';
        $this->whacenter->sendMessage($phone, "🚫 Akun WhatsApp kamu telah *diblokir* dari Marketplace Jamaah bot.\n\nAlasan: {$reason}\n\nHubungi admin jika ada pertanyaan.");
        return ['banned' => $phone];
    }

    private function execUnbanUser(array $p): array
    {
        $phone = $this->normalizePhone($p['phone'] ?? '');
        if (!$phone) {
            $this->reply('⚠️ Nomor HP tidak ditemukan dalam perintah.');
            return ['error' => 'no_phone'];
        }

        $contact = Contact::where('phone_number', $phone)->first();
        if (!$contact) {
            $this->reply("⚠️ Kontak *{$phone}* tidak ada di database.");
            return ['error' => 'not_found'];
        }

        $contact->update(['is_blocked' => false, 'warning_count' => 0]);
        $name = $contact->name ?? $phone;
        $this->reply("✅ Blokir untuk *{$name}* ({$phone}) telah dibuka.");
        return ['unbanned' => $phone];
    }

    private function execDeleteListing(array $p): array
    {
        $id = (int) ($p['listing_id'] ?? 0);
        if (!$id) {
            $this->reply('⚠️ ID iklan tidak ditemukan dalam perintah.');
            return ['error' => 'no_id'];
        }

        $listing = Listing::find($id);
        if (!$listing) {
            $this->reply('⚠️ Iklan #' . str_pad($id, 5, '0', STR_PAD_LEFT) . ' tidak ditemukan.');
            return ['error' => 'not_found'];
        }

        $title = $listing->title ?? 'Tanpa judul';
        $listing->delete();
        $this->reply("🗑️ Iklan *#{$id}* — _{$title}_ berhasil dihapus.");
        return ['deleted_listing' => $id];
    }

    private function execKickMember(array $p): array
    {
        $phone = $this->normalizePhone($p['phone'] ?? '');
        if (!$phone) {
            $this->reply('⚠️ Nomor HP tidak ditemukan dalam perintah.');
            return ['error' => 'no_phone'];
        }

        $group = WhatsappGroup::where('is_active', true)->first();
        if (!$group) {
            $this->reply('⚠️ Grup aktif tidak ditemukan.');
            return ['error' => 'no_group'];
        }

        $result = $this->whacenter->kickMember($group->group_id, $phone);
        $success = $result['success'] ?? false;

        if ($success) {
            $this->reply("✅ *{$phone}* berhasil dikeluarkan dari grup.");
        } else {
            $err = $result['data']['error'] ?? 'unknown';
            $this->reply("⚠️ Gagal mengeluarkan *{$phone}*: {$err}");
        }
        return ['kicked' => $phone, 'result' => $result];
    }

    private function execBroadcast(array $p): array
    {
        $text = $p['message'] ?? '';
        if (!$text) {
            $this->reply('⚠️ Teks broadcast tidak ditemukan dalam perintah.');
            return ['error' => 'no_message'];
        }

        $group = WhatsappGroup::where('is_active', true)->first();
        if (!$group) {
            $this->reply('⚠️ Grup aktif tidak ditemukan.');
            return ['error' => 'no_group'];
        }

        $this->whacenter->sendGroupMessage($group->group_name, $text);
        $this->reply("📣 Broadcast berhasil dikirim ke grup *{$group->group_name}*.");
        return ['broadcast_sent_to_group' => $group->group_name];
    }

    private function execHealthCheck(): array
    {
        $issues = [];

        // 1. Auto-approve semua kontak yang nyangkut: pending + stuck (null & belum registered).
        // Kebijakan no-manual-approval: anggota grup auto-valid, tidak perlu balas DM.
        $autoApproved = Contact::where('is_blocked', false)
            ->where(function ($q) {
                $q->where('onboarding_status', 'pending')
                  ->orWhere(function ($q2) {
                      $q2->whereNull('onboarding_status')->where('is_registered', false);
                  });
            })
            ->update(['onboarding_status' => 'completed', 'is_registered' => true]);

        // 2. Kontak dengan warning tinggi tapi belum banned
        $highWarning = Contact::where('warning_count', '>=', 2)
            ->where('is_blocked', false)
            ->get(['name', 'phone_number', 'warning_count', 'total_violations']);

        // 4. Listing tanpa kategori atau tanpa harga (data tidak lengkap)
        $incompleteListing = Listing::where(function ($q) {
            $q->whereNull('price')->orWhereNull('category_id');
        })
            ->where('status', 'active')
            ->count();

        // 5. Antrean job stuck (failed jobs)
        $failedJobs = DB::table('failed_jobs')->count();

        // Build report
        $report = "🏥 *Health Check Marketplace Jamaah*\n";
        $report .= '_' . now()->format('d/m/Y H:i') . ' WIB_' . "\n\n";

        // Auto-approval notice (kalau ada yang baru saja diapprove tahap ini)
        if ($autoApproved > 0) {
            $report .= "✅ *Auto-approved {$autoApproved} kontak* (pending + stuck) — kebijakan no-manual-approval.\n\n";
        }

        // High warning
        if ($highWarning->count()) {
            $count = $highWarning->count();
            $report .= "⚠️ *Peringatan tinggi — perlu perhatian admin ({$count} orang):*\n";
            foreach ($highWarning as $c) {
                $report .= "   • *{$c->name}* ({$c->phone_number}) — {$c->warning_count}x peringatan, {$c->total_violations}x pelanggaran\n";
            }
            $report .= "\n";
            $issues[] = 'high_warning';
        }

        // Incomplete listings
        if ($incompleteListing > 0) {
            $report .= "📦 *Iklan data tidak lengkap:* {$incompleteListing} iklan aktif tanpa harga/kategori\n\n";
            $issues[] = 'incomplete_listings';
        }

        // Failed jobs
        if ($failedJobs > 0) {
            $report .= "🔧 *Failed jobs di antrean:* {$failedJobs} job gagal\n\n";
            $issues[] = 'failed_jobs';
        }

        if (empty($issues)) {
            $report .= '✅ Semua beres! Tidak ada item yang perlu perhatian admin.';
        } else {
            $report .= '_Total ' . count($issues) . ' issue ditemukan._';
        }

        $this->reply($report);
        return ['issues' => $issues];
    }

    private function execStatus(): array
    {
        $totalListings = Listing::count();
        $activeListings = Listing::where('status', 'active')->count();
        $totalContacts = Contact::count();
        $registeredUsers = Contact::where('is_registered', true)->count();
        $blockedUsers = Contact::where('is_blocked', true)->count();
        $todayMessages = Message::whereDate('created_at', today())->count();
        $groups = WhatsappGroup::where('is_active', true)->count();

        $report = "📊 *Status Sistem Marketplace Jamaah*\n\n"
            . "📦 Iklan total: *{$totalListings}* (aktif: {$activeListings})\n"
            . "👥 Total kontak: *{$totalContacts}*\n"
            . "✅ Terdaftar: *{$registeredUsers}*\n"
            . "🚫 Diblokir: *{$blockedUsers}*\n"
            . "💬 Pesan hari ini: *{$todayMessages}*\n"
            . "🏘️ Grup aktif: *{$groups}*\n"
            . '_Report: ' . now()->format('d/m/Y H:i') . ' WIB_';

        $this->reply($report);
        return ['status' => 'ok'];
    }

    private function execSystemHealthReport(): array
    {
        Artisan::call('monitor:run', ['--force' => true]);
        return ['system_health_report' => 'sent'];
    }

    private function execHelp(): array
    {
        $help = "🤖 *Daftar Perintah Master*\n\n"
            . "Tulis perintah bebas, contoh:\n\n"
            . "• _System health report_ — laporan teknis (server, gateway, CPU, RAM, dll)\n"
            . "• _Health check_ — laporan bisnis (onboarding, warning, iklan)\n"
            . "• _Status sistem_ — statistik singkat\n"
            . "• _Kirim pesan ke 08xxx: \"halo kak\"_\n"
            . "• _Kirim ke grup: \"Selamat pagi semua!\"_\n"
            . "• _Ban user 08xxx karena spam_\n"
            . "• _Unban 08xxx_\n"
            . "• _Hapus iklan #12_\n"
            . "• _Keluarkan 08xxx dari grup_\n"
            . "• _Broadcast: \"Info penting...\"_\n\n"
            . '_Semua perintah diproses oleh AI — tulis dengan natural dalam Bahasa Indonesia._ 🙏';

        $this->reply($help);
        return ['help' => 'shown'];
    }

    private function execUnknown(string $command): array
    {
        // Fallback: biarkan Gemini menjawab sebagai asisten umum
        $promptTemplate = Setting::get('prompt_master_fallback', 'Kamu adalah asisten admin marketplace WhatsApp bernama Jamaah Bot. Admin mengirim pesan: "{command}". Balas dengan sopan dan helpful dalam Bahasa Indonesia (max 3 kalimat).');
        $prompt = str_replace('{command}', $command, $promptTemplate);
        $reply = $this->gemini->generateContent($prompt) ?? 'Maaf, perintah tidak dikenali. Ketik *bantuan* untuk melihat daftar perintah.';
        $this->reply($reply);
        return ['unknown_handled' => true];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function reply(string $text): void
    {
        $this->whacenter->sendMessage(config('services.wa_gateway.master_phone', ''), $text);
    }

    private function normalizePhone(string $phone): string
    {
        if (!$phone)
            return '';
        // Remove +, spaces, dashes
        $phone = preg_replace('/[\s\-\+]/', '', $phone);
        // Convert 08xxx to 628xxx
        if (str_starts_with($phone, '08')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }
}
