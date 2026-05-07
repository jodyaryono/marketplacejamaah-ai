<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class Claw3dController extends Controller
{
    public function __construct(private GeminiService $gemini) {}

    private function agents(): array
    {
        return [
            'member-onboarding'   => 'Menyambut anggota baru grup WA, mengirim DM onboarding, dan memandu proses registrasi.',
            'whatsapp-listener'   => 'Pengamat utama traffic WhatsApp: menangkap setiap pesan masuk dan merutekan ke agent yang tepat.',
            'message-parser'      => 'Memecah pesan mentah WA menjadi intent, entitas, dan metadata yang terstruktur.',
            'message-moderation'  => 'Memfilter pesan berbahaya, spam, atau di luar topik sebelum diproses lebih jauh.',
            'ad-classifier'       => 'Mengidentifikasi apakah sebuah pesan adalah iklan jual/beli dan ke kategori mana.',
            'ad-builder'          => 'Menyusun listing produk dari teks iklan yang belum terstruktur.',
            'image-analyzer'      => 'Menganalisa gambar yang dikirim anggota: produk, KTP, bukti transfer, dll.',
            'ktp-scan'            => 'Ekstraksi data KTP untuk verifikasi identitas penjual.',
            'data-extractor'      => 'Ekstraksi field terstruktur (harga, lokasi, kontak) dari teks bebas.',
            'search'              => 'Pencarian listing marketplace menggunakan intent natural language.',
            'location'            => 'Memahami dan menormalkan lokasi anggota ke area jangkauan marketplace.',
            'listing-edit'        => 'Menangani perintah edit, perpanjang, atau hapus listing dari penjual.',
            'bot-query'           => 'Menjawab pertanyaan umum anggota tentang cara pakai marketplace jamaah.',
            'group-admin-reply'   => 'Membalas otomatis perintah admin di grup untuk moderasi dan kontrol.',
            'broadcast'           => 'Menyebar pengumuman / promo ke grup atau kontak yang relevan.',
            'master-command'      => 'Command router tingkat admin / owner untuk operasi manajemen seluruh sistem.',
        ];
    }

    private function agentNameToRole(string $name): string
    {
        $name = preg_replace('/Agent$/', '', $name);
        $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $name));
        return $kebab ?: 'master-command';
    }

    public function activity(Request $request): JsonResponse
    {
        $since = (float) $request->query('since', 0);
        $limit = min((int) $request->query('limit', 100), 500);

        $query = AgentLog::query()
            ->orderBy('id', 'asc')
            ->limit($limit);

        if ($since > 0) {
            $query->where('created_at', '>', date('Y-m-d H:i:s.u', (int) $since));
        }

        $logs = $query->get();
        $knownRoles = array_keys($this->agents());

        $items = $logs->map(function (AgentLog $log) use ($knownRoles) {
            $role = $this->agentNameToRole($log->agent_name ?? '');
            if (! in_array($role, $knownRoles, true)) {
                $role = 'master-command';
            }
            $input = $log->input_payload ?? [];
            $output = $log->output_payload ?? [];
            $preview = '';
            if (is_array($input)) {
                foreach (['text', 'message', 'body', 'content'] as $k) {
                    if (!empty($input[$k]) && is_string($input[$k])) {
                        $preview = $input[$k];
                        break;
                    }
                }
            }
            $reply = '';
            if (is_array($output)) {
                foreach (['text', 'reply', 'content', 'result'] as $k) {
                    if (!empty($output[$k]) && is_string($output[$k])) {
                        $reply = $output[$k];
                        break;
                    }
                }
            }

            return [
                'id' => $log->id,
                'role' => $role,
                'agent_name' => $log->agent_name,
                'status' => $log->status,
                'message_id' => $log->message_id,
                'duration_ms' => $log->duration_ms,
                'error' => $log->error,
                'input_preview' => mb_substr($preview, 0, 240),
                'output_preview' => mb_substr($reply, 0, 240),
                'created_at' => $log->created_at?->timestamp,
                'created_at_iso' => optional($log->created_at)->toIso8601String(),
            ];
        });

        return response()->json([
            'ts' => time(),
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function simulate(Request $request): JsonResponse
    {
        $agentsMap = $this->agents();
        $roles = array_keys($agentsMap);
        $count = min(max((int) $request->input('count', 1), 1), 20);

        $scenarios = [
            ['message-parser', 'Ada iPhone 13 second bismillah harga 7jt minat?', 'Parsed: kategori=elektronik, harga=7000000, produk=iphone 13'],
            ['ad-classifier', 'Jual beras organik 25kg murah akhi/ukhti', 'Classified as: ad=true, category=makanan'],
            ['message-moderation', 'Promo pinjol cepat cair tanpa bi checking', 'Blocked: spam/keuangan terlarang'],
            ['search', 'nyari kompor gas 2 tungku di area jakarta timur', 'Found 3 listings within 8km radius'],
            ['location', 'lokasi saya di dekat masjid sunda kelapa', 'Resolved: Jakarta Pusat, Menteng'],
            ['image-analyzer', '(user kirim foto produk)', 'Detected: sepatu olahraga, kondisi bagus'],
            ['ktp-scan', '(user kirim foto KTP)', 'Verified: NIK valid, nama cocok dengan profile WA'],
            ['ad-builder', 'Jual kulkas samsung 2 pintu mulus pemakaian 1thn', 'Listing draft: judul, harga, foto, deskripsi tersusun'],
            ['listing-edit', 'Akhi, tolong perpanjang iklan saya yg kulkas', 'Extended: listing#142 +7 days'],
            ['bot-query', 'bagaimana cara pasang iklan di sini?', 'Replied with guide: /panduan-jual'],
            ['broadcast', '(admin schedule)', 'Broadcast sent to 340 members'],
            ['group-admin-reply', '/ban @user123', 'Banned user by admin request'],
            ['member-onboarding', '(member baru join grup)', 'Sent onboarding DM'],
            ['whatsapp-listener', '(incoming message)', 'Routed to message-parser'],
            ['data-extractor', 'Contact: 08123456789, alamat: Jl Mawar 12', 'Extracted: phone, address'],
            ['master-command', '/stats', 'Reported: 24h metrics'],
        ];

        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $scene = $scenarios[array_rand($scenarios)];
            [$role, $input, $output] = $scene;
            $agentName = str_replace(' ', '', ucwords(str_replace('-', ' ', $role))) . 'Agent';

            $log = AgentLog::create([
                'agent_name' => $agentName,
                'message_id' => null,
                'input_payload' => ['text' => $input, 'simulated' => true],
                'output_payload' => ['text' => $output, 'simulated' => true],
                'status' => 'success',
                'duration_ms' => random_int(80, 2400),
                'retry_count' => 0,
            ]);
            $created[] = ['id' => $log->id, 'role' => $role, 'agent_name' => $agentName];
        }

        return response()->json(['created' => count($created), 'items' => $created]);
    }

    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'status' => 'ok',
            'service' => 'marketplace-jamaah-ai',
        ]);
    }

    public function state(): JsonResponse
    {
        $model = config('services.gemini.model', 'gemini-flash-latest');

        $active = [];
        foreach (array_keys($this->agents()) as $role) {
            $active[$role] = $model;
        }

        return response()->json([
            'profile' => 'marketplace-jamaah',
            'profileName' => 'Marketplace Jamaah AI',
            'active' => $active,
            'identity' => [
                'name' => 'Marketplace Jamaah AI',
                'role' => 'orchestrator',
                'lane' => 'marketplace',
                'model_id' => $model,
            ],
            'runtime' => [
                'name' => 'Marketplace Jamaah AI',
                'version' => '1.0.0',
                'vendor' => 'Marketplace Jamaah',
                'status' => 'success',
                'active_model' => $model,
                'governance' => 'community-driven',
            ],
        ]);
    }

    public function registry(): JsonResponse
    {
        $model = config('services.gemini.model', 'gemini-flash-latest');

        $agents = $this->agents();
        $models = [];
        foreach ($agents as $role => $desc) {
            $models[$role] = [
                'id' => $role,
                'label' => ucwords(str_replace('-', ' ', $role)) . ' Agent',
                'description' => $desc,
                'backend_model' => $model,
            ];
        }

        return response()->json([
            'profile' => 'marketplace-jamaah',
            'models' => $models,
        ]);
    }

    public function chatCompletions(Request $request): JsonResponse
    {
        $role = trim((string) ($request->input('role') ?? $request->input('lane') ?? 'master-command'));
        $agents = $this->agents();
        if (! isset($agents[$role])) {
            $role = 'master-command';
        }
        $persona = $agents[$role];
        $messages = $request->input('messages', []);

        $transcript = '';
        foreach ($messages as $m) {
            $r = $m['role'] ?? 'user';
            $c = is_string($m['content'] ?? null) ? $m['content'] : '';
            $transcript .= strtoupper($r) . ": " . $c . "\n";
        }

        $prompt = "Kamu adalah agent AI bernama \"" . ucwords(str_replace('-', ' ', $role)) . "\" di dalam sistem Marketplace Jamaah AI (marketplace berbasis grup WhatsApp untuk komunitas jamaah Indonesia).\n"
            . "Peranmu: {$persona}\n"
            . "Jawab dalam Bahasa Indonesia, singkat, in-character sesuai peran di atas. Jika pertanyaan di luar konteks peranmu, arahkan ke agent yang lebih tepat.\n\n"
            . "Percakapan sejauh ini:\n{$transcript}"
            . "ASSISTANT:";

        try {
            $reply = $this->gemini->generateContent($prompt);
        } catch (\Throwable $e) {
            $reply = null;
        }

        if (! is_string($reply) || trim($reply) === '') {
            $reply = "(" . ucwords(str_replace('-', ' ', $role)) . " tidak dapat merespon saat ini — cek koneksi Gemini/Groq di .env)";
        }

        return response()->json([
            'id' => 'chatcmpl-' . bin2hex(random_bytes(8)),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => $request->input('model') ?: config('services.gemini.model', 'gemini-flash-latest'),
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $reply,
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ],
        ]);
    }
}
