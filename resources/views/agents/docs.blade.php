@extends('layouts.app')

@section('title', 'Dokumentasi AI Agent')

@section('content')
<style>
    /* ── Page wrapper ── */
    .docs-page {
        padding: 0.5rem 0.75rem;
    }

    /* ── Pipeline ── */
    .pipe-wrap {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 1px 6px rgba(0,0,0,.06);
        padding: 2rem 2.5rem 1.5rem;
        margin-bottom: 2rem;
    }
    .pipe-step {
        display: flex;
        gap: 1.4rem;
        position: relative;
        cursor: pointer;
        padding: .9rem 1.2rem;
        border-radius: 12px;
        transition: background .15s;
        margin-bottom: .35rem;
    }
    .pipe-step:hover { background: #f0fdf4; }
    .pipe-step.is-open { background: #ecfdf5; margin-bottom: .5rem; }
    .pipe-timeline {
        width: 3px;
        background: linear-gradient(#059669, #10b981);
        border-radius: 2px;
        position: relative;
        flex-shrink: 0;
        margin-left: 20px;
    }
    .pipe-step:last-child .pipe-timeline { background: transparent; }
    .pipe-dot {
        width: 16px; height: 16px;
        border-radius: 50%;
        border: 3px solid #059669;
        background: #fff;
        position: absolute;
        left: -6.5px; top: 14px;
        z-index: 1;
        transition: all .2s;
    }
    .pipe-dot.filled { background: #059669; }
    .pipe-step.is-open .pipe-dot { transform: scale(1.3); box-shadow: 0 0 0 4px rgba(5,150,105,.15); }
    .pipe-body { flex: 1; padding: .5rem 0 .8rem; }
    .pipe-name {
        font-size: .95rem;
        font-weight: 700;
        color: #111827;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .pipe-name .chevron {
        margin-left: auto;
        font-size: .7rem;
        color: #9ca3af;
        transition: transform .2s;
    }
    .pipe-step.is-open .pipe-name .chevron { transform: rotate(90deg); color: #059669; }
    .pipe-short {
        font-size: .82rem;
        color: #6b7280;
        margin-top: 6px;
    }
    .pipe-cond {
        font-size: .74rem;
        color: #059669;
        font-style: italic;
        margin-top: 4px;
    }
    .pipe-badge {
        font-size: .6rem;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        letter-spacing: .03em;
    }

    /* ── Detail Panel (collapsible) ── */
    .agent-detail {
        display: none;
        margin-top: 1.2rem;
        padding: 1.8rem 2rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        box-shadow: 0 2px 12px rgba(0,0,0,.05);
        animation: slideDown .25s ease;
    }
    .pipe-step.is-open .agent-detail { display: block; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .detail-desc {
        font-size: .88rem;
        color: #374151;
        line-height: 1.75;
        margin-bottom: 1.4rem;
        padding-bottom: 1.2rem;
        border-bottom: 1px solid #f3f4f6;
    }

    /* ── Tabs inside detail ── */
    .detail-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #e5e7eb;
        margin-bottom: 1.4rem;
    }
    .detail-tab {
        padding: .6rem 1.4rem;
        font-size: .8rem;
        font-weight: 600;
        color: #9ca3af;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        transition: all .15s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .detail-tab:hover { color: #374151; }
    .detail-tab.active { color: #059669; border-bottom-color: #059669; }
    .detail-pane { display: none; padding-top: .4rem; }
    .detail-pane.active { display: block; animation: fadeIn .2s; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    /* ── Task tags ── */
    .task-wrap { display: flex; flex-wrap: wrap; gap: .6rem; padding: .3rem 0; }
    .task-tag {
        font-size: .76rem;
        background: #f0fdf4;
        color: #166534;
        border: 1px solid #bbf7d0;
        padding: 6px 16px;
        border-radius: 20px;
        font-weight: 500;
    }

    /* ── IO boxes ── */
    .io-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.2rem;
    }
    @media (max-width: 768px) { .io-grid { grid-template-columns: 1fr; } }
    .io-box {
        background: #f9fafb;
        border-radius: 12px;
        padding: 1.4rem 1.5rem;
        border: 1px solid #e5e7eb;
    }
    .io-box h5 {
        font-size: .74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: .8rem;
        display: flex; align-items: center; gap: 6px;
    }
    .io-box ul { margin: 0; padding: 0; list-style: none; }
    .io-box ul li {
        font-size: .82rem;
        color: #374151;
        padding: 5px 0;
        line-height: 1.6;
        padding-left: 16px;
        position: relative;
    }
    .io-box ul li::before {
        content: '';
        width: 6px; height: 6px;
        border-radius: 50%;
        position: absolute;
        left: 0; top: 11px;
    }
    .io-box.input-box ul li::before { background: #3b82f6; }
    .io-box.output-box ul li::before { background: #059669; }

    /* ── Settings pills ── */
    .settings-wrap { padding: .3rem 0; }
    .setting-pill {
        font-size: .74rem;
        background: #fef3c7;
        color: #92400e;
        padding: 5px 12px;
        border-radius: 6px;
        display: inline-block;
        margin: 3px 6px 3px 0;
        font-family: monospace;
    }

    /* ── Summary cards ── */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.2rem;
        margin-bottom: 2.5rem;
    }
    .summary-card {
        background: #fff;
        border-radius: 14px;
        padding: 1.5rem 1.2rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.06);
        text-align: center;
    }
    .summary-card .num {
        font-size: 2rem;
        font-weight: 800;
        line-height: 1;
    }
    .summary-card .lbl {
        font-size: .8rem;
        color: #6b7280;
        margin-top: 6px;
    }
</style>

{{-- ════════ Header ════════ --}}
<div class="docs-page">
<div class="d-flex justify-content-between align-items-center" style="margin-bottom:1.8rem;">
    <div>
        <h4 class="fw-bold" style="color:#111827;margin-bottom:.4rem;">
            <i class="bi bi-book" style="color:#059669;"></i> Dokumentasi AI Agent
        </h4>
        <p class="text-muted mb-0" style="font-size:.85rem;">Klik agent di pipeline untuk melihat penjelasan lengkap tugas, input & output</p>
    </div>
    <a href="{{ route('agents.index') }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;padding:.5rem 1rem;">
        <i class="bi bi-arrow-left me-1"></i>Kembali ke Monitor
    </a>
</div>

{{-- ════════ Summary Cards ════════ --}}
<div class="summary-grid">
    <div class="summary-card">
        <div class="num" style="color:#059669;">11</div>
        <div class="lbl">Total Agent</div>
    </div>
    <div class="summary-card">
        <div class="num" style="color:#7c3aed;">7</div>
        <div class="lbl">Pakai Gemini AI</div>
    </div>
    <div class="summary-card">
        <div class="num" style="color:#f59e0b;">22</div>
        <div class="lbl">Editable Prompt/Config</div>
    </div>
    <div class="summary-card">
        <div class="num" style="color:#3b82f6;">5</div>
        <div class="lbl">Selalu Aktif</div>
    </div>
</div>

{{-- ════════ Interactive Pipeline ════════ --}}
@php
$agents = [
    [
        'name' => 'WhatsAppListenerAgent', 'gemini' => false, 'order' => 1, 'always' => true,
        'short' => 'Terima webhook → normalisasi → simpan pesan & kontak ke DB',
        'cond' => 'Selalu jalan untuk semua pesan masuk',
        'summary' => 'Entry point utama untuk semua pesan WhatsApp. Menerima webhook dari Whacenter/Baileys gateway, normalisasi payload, dan menyimpan ke database. Agent ini juga melakukan deduplikasi (hapus pesan duplikat dalam 24 jam), one-liner guard (hapus pesan terlalu pendek dari grup), dan resolusi LID (Linked ID) ke nomor telepon asli.',
        'tasks' => ['Terima webhook payload', 'Normalisasi data pesan', 'Buat/update Contact', 'Buat/update WhatsappGroup', 'Simpan Message ke DB', 'Deteksi & hapus duplikat', 'One-liner guard', 'Resolusi LID ke nomor asli', 'Forward media dari gateway'],
        'inputs' => ['Raw webhook payload (array): sender, message, group_id, message_id, media_url, timestamp, type'],
        'outputs' => ['Message object (atau null jika skip)', 'Side effect: buat/update Contact, WhatsappGroup, Message di database', 'Side effect: hapus pesan duplikat/one-liner dari grup'],
        'settings' => ['template_duplicate_with_listing', 'template_duplicate_no_listing'],
    ],
    [
        'name' => 'MasterCommandAgent', 'gemini' => true, 'order' => 2, 'always' => false,
        'short' => 'Parse & eksekusi perintah master admin via Gemini',
        'cond' => 'Hanya jika pengirim = nomor master. BLOKIR pipeline lain.',
        'summary' => 'Agent khusus untuk perintah dari nomor master/owner. Hanya aktif jika pengirim adalah nomor master yang sudah dikonfigurasi. Menggunakan Gemini untuk parsing perintah natural language dan mengeksekusi berbagai aksi admin: health check, kirim DM/broadcast, ban/unban user, hapus iklan, kick anggota dari grup.',
        'tasks' => ['Parse perintah via Gemini', 'Health check semua service', 'Kirim DM ke member', 'Broadcast ke semua grup', 'Ban/unban contact', 'Hapus listing', 'Kick anggota dari grup', 'Laporan status sistem'],
        'inputs' => ['Message dari nomor master (DM)'],
        'outputs' => ['true (selalu consumed)', 'Side effect: kirim reply ke master', 'Side effect: ban/unban contact, hapus listing, kick member'],
        'settings' => ['prompt_master_command', 'prompt_master_fallback'],
    ],
    [
        'name' => 'MemberOnboardingAgent', 'gemini' => true, 'order' => 3, 'always' => false,
        'short' => 'Kirim DM onboarding, kumpulkan nama/kota/role via Gemini',
        'cond' => 'Jika member baru / belum registrasi',
        'summary' => 'Mengurus pendaftaran anggota baru marketplace melalui alur DM multi-langkah. Saat member baru muncul di grup, agent ini mengirim DM sapaan dan meminta data: nama, kota, role (penjual/pembeli/keduanya). Menggunakan Gemini untuk parsing jawaban natural language. Setelah registrasi selesai, pesan yang tertunda akan diproses ulang.',
        'tasks' => ['Deteksi member baru di grup', 'Kirim DM sapaan onboarding', 'Kumpulkan nama, kota, role via chat', 'Tanya produk yang dijual/dicari', 'Tandai member sebagai registered', 'Proses ulang pesan tertunda', 'Handle approval join request grup'],
        'inputs' => ['Message — grup (deteksi member baru) atau DM (lanjut onboarding)'],
        'outputs' => ['true/false (apakah DM di-handle sebagai onboarding)', 'Side effect: update Contact (nama, alamat, role, onboarding_status)', 'Side effect: kirim serangkaian DM ke member baru'],
        'settings' => ['prompt_onboarding_chat', 'prompt_onboarding_products', 'prompt_onboarding_approval'],
    ],
    [
        'name' => 'MessageParserAgent', 'gemini' => false, 'order' => 4, 'always' => true,
        'short' => 'Parse struktur pesan: tipe, harga, kontak, word count',
        'cond' => 'Selalu jalan untuk pesan grup',
        'summary' => 'Agent parser ringan tanpa API eksternal. Menganalisis struktur pesan: mendeteksi tipe konten, keberadaan harga, nomor kontak, dan metadata lainnya. Regex untuk deteksi harga dan kontak bisa dikonfigurasi dari panel admin.',
        'tasks' => ['Parse tipe pesan (text/image/video/document)', 'Deteksi harga (regex configurable)', 'Deteksi nomor kontak/telepon (regex configurable)', 'Hitung jumlah kata', 'Deteksi keberadaan media'],
        'inputs' => ['Message object'],
        'outputs' => ['Array: type, text_content, has_media, media_url, has_price, has_contact, word_count'],
        'settings' => ['config_price_regex', 'config_contact_regex'],
    ],
    [
        'name' => 'AdClassifierAgent', 'gemini' => true, 'order' => 5, 'always' => true,
        'short' => 'Klasifikasi iklan/bukan via Gemini + fallback heuristic',
        'cond' => 'Selalu jalan untuk pesan grup',
        'summary' => 'Mengklasifikasi apakah pesan adalah iklan menggunakan Gemini AI dengan confidence score 0–100%. Memiliki one-liner guard (skip pesan <4 kata / <10 karakter). Jika Gemini gagal, otomatis fallback ke heuristic matching (kata kunci iklan: jual, harga, WTS, dll). Update flag is_ad pada record Message.',
        'tasks' => ['One-liner guard (<4 kata / <10 char)', 'Klasifikasi iklan via Gemini prompt', 'Fallback heuristic jika AI gagal', 'Update Message.is_ad', 'Update group ad_count'],
        'inputs' => ['Message object', 'Parsed data (array dari MessageParserAgent)'],
        'outputs' => ['Array: is_ad (bool), confidence (float 0-1), reason (string)', 'Side effect: update Message.is_ad, Message.is_ad_confidence'],
        'settings' => ['prompt_ad_classifier'],
    ],
    [
        'name' => 'BotQueryAgent', 'gemini' => true, 'order' => 6, 'always' => false,
        'short' => 'Jawab DM member: cari produk, scan KTP, obrolan via Gemini',
        'cond' => 'Hanya untuk DM dari member terdaftar',
        'summary' => 'Menjawab pertanyaan member via DM menggunakan Gemini. Mendukung banyak intent: cari produk/penjual, list kategori, lihat iklan saya, bantuan, hubungi admin, scan KTP. Implementasi RAG (Retrieval-Augmented Generation): ambil kandidat listing dari DB, minta Gemini ranking relevansi. Handle juga pesan lokasi (reverse geocode) dan follow-up flow.',
        'tasks' => ['Deteksi intent via Gemini', 'RAG: cari & ranking listing dari DB', 'Reverse geocode lokasi via Gemini', 'Scan KTP: extract data identitas dari foto', 'Conversational follow-up (klarifikasi)', 'List kategori & listing saya', 'Fallback: obrolan umum'],
        'inputs' => ['Message DM dari member terdaftar'],
        'outputs' => ['true/false (apakah di-handle sebagai query)', 'Side effect: kirim reply DM ke member', 'Side effect: cache pending state (lokasi, klarifikasi, KTP)'],
        'settings' => ['prompt_bot_intent', 'prompt_bot_rag_relevance', 'prompt_bot_reverse_geocode', 'prompt_bot_ktp_scan', 'prompt_bot_conversation'],
    ],
    [
        'name' => 'MessageModerationAgent', 'gemini' => true, 'order' => 7, 'always' => true,
        'short' => 'Moderasi konten: deteksi spam, hinaan, scam via Gemini',
        'cond' => 'Selalu jalan untuk pesan grup',
        'summary' => 'Moderasi konten menggunakan Gemini: mendeteksi spam, hinaan, ujaran kebencian, scam (MLM, investasi bodong), dan konten tidak pantas. Quick-pass untuk iklan legit (skip scan penuh). Kategorisasi ke 8 tipe pesan. Hitung peringatan per kontak dengan auto-reset setelah X hari.',
        'tasks' => ['Quick-pass iklan legitimate', 'Deteksi 8 kategori pelanggaran via Gemini', 'Cek indikator scam pada iklan', 'Hitung severity (rendah/sedang/tinggi)', 'Increment warning_count pada Contact', 'Auto-reset warning setelah X hari', 'Generate reply DM text untuk pelanggar'],
        'inputs' => ['Message object'],
        'outputs' => ['Array: category, is_violation, violation_severity, violation_reason, reply_dm_text, language_tone', 'Side effect: update Contact.warning_count, total_violations, last_warning_at'],
        'settings' => ['prompt_moderation'],
    ],
    [
        'name' => 'DataExtractorAgent', 'gemini' => true, 'order' => 8, 'always' => false,
        'short' => 'Ekstrak data iklan terstruktur (judul, harga, kategori, dll)',
        'cond' => 'Hanya jika pesan terklasifikasi sebagai iklan',
        'summary' => 'Mengekstrak data iklan terstruktur dari teks bebas menggunakan Gemini: judul, deskripsi, harga, kategori, info kontak, lokasi, kondisi barang. Bisa fetch konten dari URL dalam pesan (max 2 URL). Smart duplicate detection (≥60% text similarity) — update listing lama alih-alih buat baru. Fallback ke listing basic jika Gemini gagal.',
        'tasks' => ['Extract data iklan via Gemini prompt', 'Fetch & parse konten dari URL', 'Match kategori dari daftar DB', 'Smart duplicate detection (text similarity)', 'Update listing lama jika duplikat', 'Buat Listing record baru', 'Validasi & filter media URL', 'Fallback basic listing jika AI gagal'],
        'inputs' => ['Message object (yang sudah terklasifikasi sebagai iklan)'],
        'outputs' => ['Listing object (atau null jika error)', 'Side effect: buat/update Listing di database'],
        'settings' => ['prompt_data_extractor'],
    ],
    [
        'name' => 'ImageAnalyzerAgent', 'gemini' => true, 'order' => 9, 'always' => false,
        'short' => 'Analisis foto produk, tingkatkan judul/deskripsi/kategori',
        'cond' => 'Hanya jika iklan punya gambar',
        'summary' => 'Analisis gambar produk menggunakan Gemini Vision API. Meningkatkan data listing: suggest judul lebih baik, deskripsi dari foto, estimasi harga, dan koreksi kategori. Perbaiki judul generik/kosong berdasarkan isi gambar. Deteksi iklan dari pesan image-only (tanpa teks). Kirim DM notifikasi jika kategori di-auto-correct.',
        'tasks' => ['Analisis gambar via Gemini Vision', 'Enrich judul & deskripsi listing', 'Koreksi kategori dari isi gambar', 'Deteksi iklan dari image-only message', 'Estimasi harga dari foto', 'Skor kualitas foto 1-5', 'DM notifikasi koreksi kategori'],
        'inputs' => ['Message object + Listing object (untuk enrichment)', 'Message object saja (untuk ad detection dari image)'],
        'outputs' => ['Array: AI analysis results (suggested title, desc, category, etc)', 'Side effect: update Listing fields', 'Side effect: kirim DM jika kategori berubah'],
        'settings' => ['prompt_image_enrichment', 'prompt_image_ad_detection'],
    ],
    [
        'name' => 'GroupAdminReplyAgent', 'gemini' => false, 'order' => 10, 'always' => false,
        'short' => 'DM konfirmasi ke penjual + handle pelanggaran/eskalasi ke admin',
        'cond' => 'Jika ada listing baru ATAU ada pelanggaran',
        'summary' => 'Mengirim DM konfirmasi ke penjual setelah iklan berhasil tayang. Menangani pelanggaran: hapus pesan dari grup, kirim warning DM ke pelanggar, eskalasi ke semua admin setelah 3 peringatan. Sistem 3-strike: blokir otomatis + kick dari grup setelah 3 warning.',
        'tasks' => ['DM konfirmasi listing ke penjual', 'Hapus pesan pelanggar dari grup', 'Kirim warning DM ke pelanggar', 'Sistem 3-strike: blokir setelah 3x', 'Kick pelanggar dari grup', 'Laporan eskalasi ke semua admin', 'Generate laporan lengkap pelanggaran'],
        'inputs' => ['Message object', 'Listing object (opsional, jika ada iklan baru)', 'Moderation result array (dari MessageModerationAgent)'],
        'outputs' => ['void', 'Side effect: kirim DM ke penjual', 'Side effect: hapus pesan, kick member, kirim laporan ke admin'],
        'settings' => ['template_listing_dm', 'template_escalation_report'],
    ],
    [
        'name' => 'BroadcastAgent', 'gemini' => false, 'order' => 11, 'always' => true,
        'short' => 'Update analytics, WebSocket event, notifikasi grup listing baru',
        'cond' => 'Selalu jalan (analytics). Notif grup jika ada listing.',
        'summary' => 'Broadcast event via WebSocket untuk update dashboard real-time. Update analytics harian (total pesan, iklan, listing per grup). Kirim notifikasi ke grup WhatsApp saat ada listing baru masuk marketplace. Berjalan paralel dengan GroupAdminReplyAgent di akhir pipeline.',
        'tasks' => ['Fire NewMessageReceived event (WebSocket)', 'Fire NewListingCreated event (WebSocket)', 'Update AnalyticsDaily per grup', 'Kirim notifikasi listing baru ke grup WA', 'Hitung total pesan, iklan, listing harian'],
        'inputs' => ['Message object', 'Listing object (opsional)'],
        'outputs' => ['void', 'Side effect: update AnalyticsDaily', 'Side effect: broadcast WebSocket events', 'Side effect: kirim notifikasi grup WA'],
        'settings' => ['template_broadcast_new_listing'],
    ],
];
@endphp

<div class="pipe-wrap">
    <div class="d-flex justify-content-between align-items-center" style="margin-bottom:1rem;">
        <h5 class="fw-bold mb-0" style="color:#111827;">
            <i class="bi bi-diagram-3" style="color:#059669;"></i> Alur Pipeline
        </h5>
        <span style="font-size:.75rem;color:#9ca3af;">Klik agent untuk lihat detail <i class="bi bi-hand-index-thumb"></i></span>
    </div>
    <p style="font-size:.84rem;color:#6b7280;margin-bottom:2rem;">
        Setiap pesan masuk dari webhook WhatsApp melewati pipeline agent secara berurutan. Klik salah satu untuk melihat penjelasan lengkap.
    </p>

    @foreach($agents as $i => $agent)
    @php $ai = $agentInfo[$agent['name']] ?? ['icon'=>'bi-robot','color'=>'#6b7280']; @endphp
    <div class="pipe-step" onclick="toggleAgent(this)" data-agent="{{ $i }}">
        <div class="pipe-timeline">
            <div class="pipe-dot {{ $agent['always'] ? 'filled' : '' }}"></div>
        </div>
        <div class="pipe-body">
            <div class="pipe-name">
                <i class="bi {{ $ai['icon'] }}" style="color:{{ $ai['color'] }};font-size:1.1rem;"></i>
                <span>#{{ $agent['order'] }}</span>
                {{ $agent['name'] }}
                @if($agent['always'])
                    <span class="pipe-badge" style="background:#dcfce7;color:#166534;">SELALU</span>
                @else
                    <span class="pipe-badge" style="background:#fef3c7;color:#92400e;">KONDISIONAL</span>
                @endif
                @if($agent['gemini'])
                    <span class="pipe-badge" style="background:#ede9fe;color:#6d28d9;"><i class="bi bi-stars"></i> AI</span>
                @endif
                <i class="bi bi-chevron-right chevron"></i>
            </div>
            <div class="pipe-short">{{ $agent['short'] }}</div>
            <div class="pipe-cond">↳ {{ $agent['cond'] }}</div>

            {{-- ── Collapsible Detail Panel ── --}}
            <div class="agent-detail" onclick="event.stopPropagation();">
                <div class="detail-desc">{{ $agent['summary'] }}</div>

                {{-- Tabs --}}
                <div class="detail-tabs">
                    <div class="detail-tab active" onclick="switchTab(event, 'tasks', {{ $i }})">
                        <i class="bi bi-check2-square"></i> Tugas
                    </div>
                    <div class="detail-tab" onclick="switchTab(event, 'io', {{ $i }})">
                        <i class="bi bi-arrow-left-right"></i> Input / Output
                    </div>
                    <div class="detail-tab" onclick="switchTab(event, 'settings', {{ $i }})">
                        <i class="bi bi-sliders"></i> Settings ({{ count($agent['settings']) }})
                    </div>
                </div>

                {{-- Tab: Tugas --}}
                <div class="detail-pane active" data-pane="tasks-{{ $i }}">
                    <div class="task-wrap">
                        @foreach($agent['tasks'] as $task)
                            <span class="task-tag">{{ $task }}</span>
                        @endforeach
                    </div>
                </div>

                {{-- Tab: Input/Output --}}
                <div class="detail-pane" data-pane="io-{{ $i }}">
                    <div class="io-grid">
                        <div class="io-box input-box">
                            <h5 style="color:#2563eb;"><i class="bi bi-box-arrow-in-right"></i> Input</h5>
                            <ul>
                                @foreach($agent['inputs'] as $input)
                                    <li>{{ $input }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="io-box output-box">
                            <h5 style="color:#059669;"><i class="bi bi-box-arrow-right"></i> Output</h5>
                            <ul>
                                @foreach($agent['outputs'] as $output)
                                    <li>{{ $output }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Tab: Settings --}}
                <div class="detail-pane" data-pane="settings-{{ $i }}">
                    @if(!empty($agent['settings']))
                        <p style="font-size:.82rem;color:#6b7280;margin-bottom:.8rem;">
                            Prompt dan konfigurasi yang bisa diedit dari halaman <strong>AI Monitor → Edit Prompt</strong>:
                        </p>
                        <div class="settings-wrap">
                            @foreach($agent['settings'] as $key)
                                <span class="setting-pill">{{ $key }}</span>
                            @endforeach
                        </div>
                    @else
                        <p style="font-size:.82rem;color:#9ca3af;font-style:italic;">Agent ini tidak punya setting yang bisa diedit.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<script>
function toggleAgent(el) {
    const wasOpen = el.classList.contains('is-open');
    // Close all
    document.querySelectorAll('.pipe-step.is-open').forEach(s => s.classList.remove('is-open'));
    // Toggle clicked
    if (!wasOpen) {
        el.classList.add('is-open');
        // Smooth scroll into view
        setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
    }
}

function switchTab(event, tabName, agentIdx) {
    event.stopPropagation();
    const step = event.target.closest('.pipe-step');
    // Deactivate all tabs & panes in this agent
    step.querySelectorAll('.detail-tab').forEach(t => t.classList.remove('active'));
    step.querySelectorAll('.detail-pane').forEach(p => p.classList.remove('active'));
    // Activate clicked tab
    event.target.closest('.detail-tab').classList.add('active');
    // Activate matching pane
    step.querySelector('[data-pane="' + tabName + '-' + agentIdx + '"]').classList.add('active');
}
</script>
</div>{{-- /.docs-page --}}
@endsection
