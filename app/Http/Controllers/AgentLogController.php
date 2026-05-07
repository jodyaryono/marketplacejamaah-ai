<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentLogController extends Controller
{
    // Deskripsi & ikon per agent — mudah ditambah jika ada agent baru
    private const AGENT_INFO = [
        'WhatsAppListenerAgent' => [
            'icon' => 'bi-broadcast',
            'color' => '#a78bfa',
            'desc' => 'Menerima & menyimpan pesan masuk dari webhook WhaCentre. Normalisasi payload, deduplikasi, buat record pesan & kontak.',
        ],
        'MessageParserAgent' => [
            'icon' => 'bi-braces',
            'color' => '#34d399',
            'desc' => 'Analisis struktur pesan tanpa API eksternal: deteksi tipe, harga, nomor kontak, panjang teks, dan kata kunci iklan.',
        ],
        'AdClassifierAgent' => [
            'icon' => 'bi-tags',
            'color' => '#fbbf24',
            'desc' => 'Klasifikasi apakah pesan adalah iklan menggunakan Gemini AI dengan confidence score 0–100%. Fallback regex jika AI gagal.',
        ],
        'MessageModerationAgent' => [
            'icon' => 'bi-shield-check',
            'color' => '#f87171',
            'desc' => 'Moderasi konten via Gemini: deteksi spam, hinaan, ujaran kebencian. Kategorisasi 8 tipe pesan. Catat pelanggaran & hitung warning.',
        ],
        'DataExtractorAgent' => [
            'icon' => 'bi-database-gear',
            'color' => '#60a5fa',
            'desc' => 'Ekstrak data iklan terstruktur (judul, harga, kategori, kontak, lokasi, kondisi) dari teks bebas menggunakan Gemini AI.',
        ],
        'ImageAnalyzerAgent' => [
            'icon' => 'bi-image',
            'color' => '#fb923c',
            'desc' => 'Analisis gambar produk menggunakan Gemini Vision: deskripsi produk, kondisi, teks terlihat, dan skor kualitas foto 1–5.',
        ],
        'BroadcastAgent' => [
            'icon' => 'bi-wifi',
            'color' => '#4ade80',
            'desc' => 'Update analytics harian & kirim event WebSocket ke dashboard real-time. Trigger notifikasi listing baru ke frontend.',
        ],
        'GroupAdminReplyAgent' => [
            'icon' => 'bi-megaphone',
            'color' => '#c084fc',
            'desc' => 'Kirim konfirmasi iklan ke grup WA & peringatan pelanggaran (DM pelanggar + notif grup). Eskalasi laporan ke semua admin setelah 3 peringatan.',
        ],
        'MemberOnboardingAgent' => [
            'icon' => 'bi-person-plus',
            'color' => '#38bdf8',
            'desc' => 'Pendaftaran anggota baru: kirim DM onboarding saat pertama kali muncul di grup, parsing nama & role (penjual/pembeli/keduanya) via Gemini.',
        ],
        'BotQueryAgent' => [
            'icon' => 'bi-chat-dots',
            'color' => '#14b8a6',
            'desc' => 'Jawab pertanyaan member via DM: cari iklan, lokasi bisnis, scan KTP, dan obrolan umum menggunakan Gemini AI.',
        ],
        'MasterCommandAgent' => [
            'icon' => 'bi-terminal',
            'color' => '#e11d48',
            'desc' => 'Proses perintah master admin via DM: health check, kirim pesan, ban/unban, hapus iklan, broadcast, dan status sistem.',
        ],
        // ── Sub-Agents (Worker) — di-invoke oleh BotQueryAgent / MasterCommandAgent ──
        'SearchAgent' => [
            'icon' => 'bi-search',
            'color' => '#06b6d4',
            'desc' => 'Worker pencarian katalog. RAG: ambil 100 kandidat listing aktif dari DB lalu ranking semantic relevansi via Gemini (cache 10 menit). List kategori, penjual top, iklan saya.',
        ],
        'LocationAgent' => [
            'icon' => 'bi-geo-alt',
            'color' => '#f59e0b',
            'desc' => 'Worker pesan lokasi GPS. Reverse-geocode lat/lng ke nama area Indonesia via Gemini (cache 30 hari). Cari produk sekitar via SearchAgent atau update lokasi bisnis Contact.',
        ],
        'KtpScanAgent' => [
            'icon' => 'bi-person-vcard',
            'color' => '#d97706',
            'desc' => 'Worker scan KTP via Gemini Vision. Ekstrak NIK, nama, TTL, alamat, agama, status, pekerjaan, masa berlaku. Reject foto non-KTP. Data tidak disimpan ke DB.',
        ],
        'ListingEditAgent' => [
            'icon' => 'bi-pencil-square',
            'color' => '#6366f1',
            'desc' => 'Worker edit iklan via DM: harga (fix/nego/lelang), status (terjual/sembunyikan/aktifkan), free-form edit via Gemini parser, on-behalf reassignment (master), auto re-post ke WAG.',
        ],
        'AdBuilderAgent' => [
            'icon' => 'bi-magic',
            'color' => '#ec4899',
            'desc' => 'Worker conversational ad builder. Wizard 3-step: foto → Gemini Vision draft AIDA → review & edit → posting ke WAG via BroadcastAgent. Halal-aware copywriting.',
        ],
    ];

    // Mapping agent name → prompt setting keys
    private const AGENT_PROMPTS = [
        'AdClassifierAgent' => ['prompt_ad_classifier'],
        'MessageModerationAgent' => ['prompt_moderation'],
        'DataExtractorAgent' => ['prompt_data_extractor'],
        'ImageAnalyzerAgent' => ['prompt_image_enrichment', 'prompt_image_ad_detection'],
        'BotQueryAgent' => ['prompt_bot_intent', 'prompt_bot_conversation'],
        'SearchAgent' => ['prompt_bot_rag_relevance'],
        'LocationAgent' => ['prompt_bot_reverse_geocode'],
        'KtpScanAgent' => ['prompt_bot_ktp_scan'],
        'MemberOnboardingAgent' => ['prompt_onboarding_chat', 'prompt_onboarding_products', 'prompt_onboarding_approval'],
        'MasterCommandAgent' => ['prompt_master_command', 'prompt_master_fallback'],
        'WhatsAppListenerAgent' => ['template_duplicate_with_listing', 'template_duplicate_no_listing'],
        'MessageParserAgent' => ['config_price_regex', 'config_contact_regex'],
        'BroadcastAgent' => ['template_broadcast_new_listing'],
        'GroupAdminReplyAgent' => ['template_listing_dm', 'template_escalation_report'],
    ];

    public function docs()
    {
        return view('agents.docs', [
            'agentInfo' => self::AGENT_INFO,
        ]);
    }

    public function index(Request $request)
    {
        $query = AgentLog::with('message')->latest();

        if ($request->filled('agent_name')) {
            $query->where('agent_name', $request->agent_name);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->paginate(30)->withQueryString();

        $agentNames = AgentLog::distinct()->pluck('agent_name');

        $stats = [
            'total' => AgentLog::count(),
            'success' => AgentLog::where('status', 'success')->count(),
            'failed' => AgentLog::where('status', 'failed')->count(),
            'processing' => AgentLog::where('status', 'processing')->count(),
            'avg_ms' => (int) AgentLog::where('status', 'success')->avg('duration_ms'),
        ];

        // Health check: last run + 24h stats per agent
        $since24h = now()->subHours(24);

        $lastRuns = AgentLog::select('agent_name',
                DB::raw('MAX(created_at) as last_run'),
                DB::raw('MAX(CASE WHEN created_at = (SELECT MAX(al2.created_at) FROM agent_logs al2 WHERE al2.agent_name = agent_logs.agent_name) THEN status END) as last_status'))
            ->groupBy('agent_name')
            ->get()
            ->keyBy('agent_name');

        $stats24h = AgentLog::select('agent_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count"),
                DB::raw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count"),
                DB::raw("ROUND(AVG(CASE WHEN status = 'success' THEN duration_ms END)) as avg_ms"))
            ->where('created_at', '>=', $since24h)
            ->groupBy('agent_name')
            ->get()
            ->keyBy('agent_name');

        $agentInfo = self::AGENT_INFO;
        $healthCheck = [];
        foreach (array_keys($agentInfo) as $name) {
            $healthCheck[$name] = [
                'info' => $agentInfo[$name],
                'last_run' => $lastRuns[$name]->last_run ?? null,
                'last_status' => $lastRuns[$name]->last_status ?? null,
                'total_24h' => $stats24h[$name]->total ?? 0,
                'success_24h' => $stats24h[$name]->success_count ?? 0,
                'failed_24h' => $stats24h[$name]->failed_count ?? 0,
                'avg_ms' => $stats24h[$name]->avg_ms ?? null,
            ];
        }

        // Load AI prompts for editing
        $promptKeys = collect(self::AGENT_PROMPTS)->flatten()->all();
        $prompts = Setting::whereIn('key', $promptKeys)->get()->keyBy('key');
        $agentPrompts = self::AGENT_PROMPTS;

        return view('agents.index', compact('logs', 'agentNames', 'stats', 'healthCheck', 'agentInfo', 'prompts', 'agentPrompts'));
    }

    public function updatePrompts(Request $request)
    {
        $data = $request->input('prompt', []);
        foreach ($data as $key => $value) {
            Setting::where('key', $key)->where('group', 'ai_prompts')->update(['value' => $value]);
        }
        // Clear setting cache
        cache()->forget('settings');

        return back()->with('prompt_saved', 'Prompt berhasil disimpan!');
    }
}
