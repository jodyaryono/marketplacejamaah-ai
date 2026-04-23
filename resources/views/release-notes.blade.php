@php
    $__loc = \App\Support\SiteLocale::get();
    $__t = fn($id, $en) => $__loc === 'en' ? $en : $id;
@endphp
<!DOCTYPE html>
<html lang="{{ $__loc }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Release Notes — MarketplaceJamaah AI</title>
    <meta name="description" content="{{ $__t('Catatan perubahan dan pembaruan fitur MarketplaceJamaah AI — semua update terbaru platform marketplace komunitas WhatsApp.','Changelog and feature updates for MarketplaceJamaah AI — all latest updates from the WhatsApp community marketplace.') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --em:      #059669;
            --em-dark: #047857;
            --em-light:#a7f3d0;
            --em-xlight:#ecfdf5;
            --em-mid:  #34d399;
            --amber:   #f59e0b;
            --amber-light:#fef3c7;
            --blue:    #3b82f6;
            --blue-light:#eff6ff;
            --purple:  #8b5cf6;
            --purple-light:#f5f3ff;
            --rose:    #f43f5e;
            --rose-light:#fff1f2;
        }
        * { box-sizing: border-box; }
        body { background: #f0fdf8; color: #111827; font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; }

        /* ── Navbar ─────────────────── */
        .site-nav {
            background: rgba(255,255,255,.92); backdrop-filter: blur(12px);
            border-bottom: 1.5px solid rgba(167,243,208,.5);
            padding: .7rem 0; position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 16px rgba(5,150,105,.1);
        }
        .brand-logo {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(5,150,105,.45); flex-shrink: 0;
        }
        .brand-logo i { color: #fff; font-size: 1.15rem; }
        .brand-name { font-size: 1.1rem; font-weight: 800; color: #111827; line-height: 1.2; }
        .brand-name span { color: var(--em); }
        .brand-sub { font-size: .65rem; color: #6b7280; font-weight: 500; }
        .btn-nav-primary {
            background: linear-gradient(135deg, #059669, #10b981, #34d399);
            color: #fff; border: none; border-radius: 10px;
            font-size: .82rem; font-weight: 700; padding: .48rem 1.2rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
            box-shadow: 0 3px 12px rgba(5,150,105,.45); transition: all .2s;
        }
        .btn-nav-primary:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(5,150,105,.5); }
        .btn-nav-outline {
            background: var(--em-xlight); color: var(--em-dark); border: 1.5px solid var(--em-light);
            border-radius: 10px; font-size: .82rem; font-weight: 700; padding: .42rem 1.1rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: .38rem;
            transition: all .18s;
        }
        .btn-nav-outline:hover { background: var(--em); color: #fff; border-color: var(--em); }

        /* ── Page Hero ───────────────── */
        .page-hero {
            background: linear-gradient(135deg, #042f24 0%, #064e3b 45%, #065f46 100%);
            padding: 4rem 0 5rem; position: relative; overflow: hidden;
        }
        .page-hero::before {
            content: ''; position: absolute; top: -60px; right: -80px;
            width: 500px; height: 500px; border-radius: 50%;
            background: radial-gradient(circle, rgba(5,150,105,.35) 0%, transparent 70%);
        }
        .page-hero .eyebrow {
            display: inline-flex; align-items: center; gap: .45rem;
            background: rgba(110,231,183,.12); border: 1.5px solid rgba(110,231,183,.4);
            color: #6ee7b7; border-radius: 100px; padding: .3rem 1.05rem;
            font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        .page-hero h1 { font-size: 2.8rem; font-weight: 900; color: #fff; line-height: 1.15; margin-bottom: 1rem; }
        .page-hero h1 em { color: var(--em-mid); font-style: normal; }
        .page-hero .lead { font-size: 1rem; color: rgba(255,255,255,.75); max-width: 580px; line-height: 1.7; }

        /* ── Timeline ────────────────── */
        .timeline { position: relative; padding: 0; }
        .timeline::before {
            content: ''; position: absolute; left: 28px; top: 0; bottom: 0;
            width: 2px; background: linear-gradient(180deg, var(--em-light), rgba(167,243,208,.2));
        }
        @media(min-width: 768px) {
            .timeline::before { left: 50%; transform: translateX(-50%); }
        }

        .rn-entry { display: flex; gap: 1.5rem; margin-bottom: 2.5rem; position: relative; }
        @media(min-width: 768px) {
            .rn-entry { align-items: flex-start; }
            .rn-entry.left  { flex-direction: row-reverse; }
            .rn-entry.right { flex-direction: row; }
        }

        .rn-dot {
            flex-shrink: 0; width: 56px; height: 56px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; border: 3px solid #fff;
            box-shadow: 0 4px 16px rgba(0,0,0,.1); z-index: 1;
        }
        .rn-dot.green  { background: linear-gradient(135deg, #059669, #10b981); color: #fff; }
        .rn-dot.amber  { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #fff; }
        .rn-dot.blue   { background: linear-gradient(135deg, #3b82f6, #60a5fa); color: #fff; }
        .rn-dot.purple { background: linear-gradient(135deg, #8b5cf6, #a78bfa); color: #fff; }
        .rn-dot.rose   { background: linear-gradient(135deg, #f43f5e, #fb7185); color: #fff; }

        .rn-card {
            background: #fff; border: 1.5px solid #e5e7eb; border-radius: 20px;
            padding: 1.6rem 1.75rem; flex: 1;
            box-shadow: 0 2px 12px rgba(0,0,0,.05);
            transition: box-shadow .2s, border-color .2s;
        }
        .rn-card:hover { box-shadow: 0 8px 32px rgba(5,150,105,.12); border-color: var(--em-mid); }

        .rn-meta {
            display: flex; align-items: center; flex-wrap: wrap; gap: .5rem;
            margin-bottom: .75rem;
        }
        .rn-version {
            font-size: .72rem; font-weight: 800; padding: .2rem .7rem; border-radius: 100px;
            text-transform: uppercase; letter-spacing: .06em;
        }
        .rn-version.latest { background: var(--em-xlight); color: var(--em-dark); border: 1px solid var(--em-light); }
        .rn-version.stable { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
        .rn-version.beta   { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
        .rn-version.fix    { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }

        .rn-date {
            font-size: .75rem; color: #9ca3af; font-weight: 600;
        }
        .rn-card h4 { font-size: 1.05rem; font-weight: 800; color: #111827; margin-bottom: .5rem; }
        .rn-card .rn-summary { font-size: .88rem; color: #6b7280; line-height: 1.65; margin-bottom: 1rem; }

        .rn-items { list-style: none; padding: 0; margin: 0; }
        .rn-items li {
            display: flex; align-items: flex-start; gap: .6rem;
            padding: .45rem 0; border-bottom: 1px solid #f3f4f6;
            font-size: .84rem; color: #374151; line-height: 1.55;
        }
        .rn-items li:last-child { border-bottom: none; }
        .rn-items li .ri-icon {
            flex-shrink: 0; width: 22px; height: 22px; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; margin-top: 1px;
        }
        .ri-icon.new   { background: #ecfdf5; color: #059669; }
        .ri-icon.fix   { background: #fff7ed; color: #ea580c; }
        .ri-icon.impr  { background: #eff6ff; color: #2563eb; }
        .ri-icon.ai    { background: #f5f3ff; color: #7c3aed; }
        .ri-icon.sec   { background: #fff1f2; color: #e11d48; }

        .tag-row { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: 1rem; }
        .rn-tag {
            font-size: .68rem; font-weight: 700; padding: .18rem .6rem; border-radius: 100px;
            border: 1px solid transparent;
        }
        .rn-tag.ai     { background: var(--em-xlight); color: var(--em-dark); border-color: var(--em-light); }
        .rn-tag.db     { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .rn-tag.ui     { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
        .rn-tag.api    { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
        .rn-tag.fix    { background: #fff1f2; color: #be123c; border-color: #fecdd3; }
        .rn-tag.infra  { background: #f9fafb; color: #374151; border-color: #d1d5db; }

        /* ── Section ─────────────────── */
        .section-eyebrow { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--em); margin-bottom: .35rem; }
        .section-divider { height: 3px; width: 60px; border-radius: 4px; background: linear-gradient(90deg, var(--em), var(--em-mid)); margin: 0 auto .75rem; }
        .section-title { font-size: 2rem; font-weight: 900; color: #111827; line-height: 1.2; }
        .section-title span { color: var(--em); }

        /* ── Footer ──────────────────── */
        .site-footer {
            background: linear-gradient(135deg, #064e3b, #065f46);
            padding: 2.5rem 0; text-align: center; margin-top: 5rem;
        }
        .site-footer .footer-logo { font-size: 1.05rem; font-weight: 800; color: #fff; }
        .site-footer .footer-logo span { color: #6ee7b7; }
        .site-footer p { font-size: .8rem; color: rgba(255,255,255,.5); margin: .3rem 0 0; }
        .site-footer a { color: #6ee7b7; text-decoration: none; font-weight: 600; }

        @media(max-width: 767px) {
            .page-hero h1 { font-size: 2rem; }
            .timeline::before { display: none; }
        }
    </style>
</head>
<body>

{{-- ── Navbar ──────────────────────────────────────────── --}}
<nav class="site-nav">
    <div class="container d-flex align-items-center justify-content-between">
        <a href="{{ url('/') }}" class="d-flex align-items-center gap-2 text-decoration-none">
            <div class="brand-logo"><i class="bi bi-shop-window"></i></div>
            <div>
                <div class="brand-name">Marketplace<span>Jamaah</span></div>
                <div class="brand-sub">AI-powered WhatsApp Marketplace</div>
            </div>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ url('/marketing-tools') }}" class="btn-nav-outline d-none d-sm-flex">
                <i class="bi bi-grid-3x3-gap"></i> Fitur
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-nav-primary">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            @endauth
        </div>
    </div>
</nav>

{{-- ── Page Hero ────────────────────────────────────────── --}}
<section class="page-hero">
    <div class="container position-relative" style="z-index:2;">
        <div class="eyebrow"><i class="bi bi-journal-code"></i>&nbsp;Changelog &bull; Release Notes</div>
        <h1>{{ $__t('Update','Updates') }} &amp; <em>Release Notes</em></h1>
        <p class="lead">
            Semua perubahan, fitur baru, perbaikan bug, dan peningkatan platform MarketplaceJamaah AI dicatat di sini secara kronologis.
        </p>
    </div>
</section>

{{-- ── Timeline ─────────────────────────────────────────── --}}
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">Changelog</div>
            <div class="section-divider"></div>
            <h2 class="section-title">Riwayat <span>Pembaruan Platform</span></h2>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="timeline">

                    {{-- ═══ v0.5.2 — Landing Page + Configurable Settings ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot green"><i class="bi bi-layout-wtf"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version latest">Latest</span>
                                <span class="rn-version stable">v0.5.2</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>16 Maret 2026</span>
                            </div>
                            <h4>🌐 Landing Page Publik & Konfigurasi Tampilan dari Admin</h4>
                            <p class="rn-summary">
                                Landing page marketplace kini tampil lebih profesional dengan hero slider iklan foto/video, bagian iklan barus, tema putih–emerald, dan font Plus Jakarta Sans. Admin kini bisa mengatur jumlah iklan yang ditampilkan langsung dari halaman Settings tanpa ubah kode.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Hero Slider Landing Page</strong> — Bagian hero kini menampilkan slider iklan foto/video aktif secara acak, memberi kesan marketplace yang hidup dan dinamis</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Iklan Barus (Text-Only)</strong> — Bagian baru di landing page untuk iklan tanpa foto/video, tampil dalam layout ringkas agar tidak memakan ruang layar</span>
                                </li>
                                <li>
                                    <span class="ri-icon impr"><i class="bi bi-arrow-up"></i></span>
                                    <span><strong>Redesign Tema Putih + Emerald</strong> — Seluruh landing page didesain ulang dengan warna dominan putih dan aksen hijau emerald, font Plus Jakarta Sans, dan layout 2-kolom hero yang lebih modern</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Setting: Jumlah Iklan dengan Media</strong> — Admin bisa atur jumlah iklan foto/video di landing page via <strong>Settings → Landing</strong> (default: 6). Perubahan berlaku real-time</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Setting: Jumlah Iklan Barus</strong> — Admin bisa atur jumlah iklan barus (tanpa media) yang tampil di landing page (default: 10)</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>Fix: Tombol Tambah Grup Tidak Muncul</strong> — Gate <code>@can('manage groups')</code> yang tidak pernah terdaftar diganti dengan pengecekan <code>role === 'admin'</code> langsung — tombol kini tampil untuk admin</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>Fix: Route Error di /listings</strong> — <code>Route [listings.update-status]</code> tidak ada — diperbaiki ke nama route yang benar <code>listings.status</code></span>
                                </li>
                                <li>
                                    <span class="ri-icon impr"><i class="bi bi-arrow-up"></i></span>
                                    <span><strong>Settings Form: Support Input Number</strong> — Form pengaturan di /settings kini mendukung tipe <code>number</code> agar input jumlah iklan tervalidasi browser secara otomatis</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag ui"><i class="bi bi-layout-wtf me-1"></i>Landing Page</span>
                                <span class="rn-tag infra"><i class="bi bi-sliders me-1"></i>Settings</span>
                                <span class="rn-tag fix"><i class="bi bi-bug me-1"></i>Bug Fix</span>
                                <span class="rn-tag db"><i class="bi bi-database me-1"></i>DB Migration</span>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ v0.5.1 — Login Member Dihapus ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot blue"><i class="bi bi-arrow-repeat"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version stable">v0.5.1</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>11 Maret 2026</span>
                            </div>
                            <h4>🔄 Login Member Dihapus — Semua via Bot</h4>
                            <p class="rn-summary">
                                Fitur Login Member (OTP via WhatsApp) dihapus karena semua fungsi member kini bisa dilakukan langsung dari chat bot WhatsApp. Tidak perlu lagi buka website untuk kelola iklan — cukup chat bot.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Zero Login Experience</strong> — Member tidak perlu login ke website. Semua aksi (lihat iklan, edit, cari produk) dilakukan via chat bot WhatsApp</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-trash3"></i></span>
                                    <span><strong>Hapus Login Member</strong> — Tombol "Login Member" dan "Masuk Member" dihapus dari navbar, hero section, dan footer di seluruh halaman</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-trash3"></i></span>
                                    <span><strong>Hapus WA OTP Auth</strong> — Halaman login OTP WhatsApp (<code>/login-wa</code>), dashboard member (<code>/saya</code>), dan edit listing via web dinonaktifkan. URL lama redirect ke homepage</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Marketing Tools Updated</strong> — Fitur "Dashboard Member via WA Login" diganti "Kelola Iklan 100% via Bot WhatsApp" di halaman marketing</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag new"><i class="bi bi-arrow-repeat me-1"></i>Simplifikasi</span>
                                <span class="rn-tag api"><i class="bi bi-whatsapp me-1"></i>WhatsApp</span>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ v0.5 — Edit Iklan via DM + Video Support ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot green"><i class="bi bi-pencil-square"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version stable">v0.5</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>10 Maret 2026</span>
                            </div>
                            <h4>✏️ Edit Iklan via Chat WhatsApp & Video Support</h4>
                            <p class="rn-summary">
                                Member kini bisa mengedit iklan langsung dari chat WhatsApp tanpa perlu buka website. Cukup ketik <code>edit #123</code>, lalu kirim perubahan dalam bahasa natural. Ditambah dukungan penuh upload dan tampilan video di seluruh platform.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Edit Iklan via DM Bot</strong> — Ketik <code>edit #ID</code> di chat pribadi bot, lalu kirim perubahan dalam bahasa bebas. AI Gemini menerjemahkan instruksi ke field yang tepat (judul, harga, deskripsi, dll.)</span>
                                </li>
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>AI Natural Language Edit</strong> — Gemini memparse instruksi seperti "harga jadi 50rb" atau "tambah deskripsi ready stock" menjadi update database yang presisi</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Ownership Protection</strong> — Hanya pemilik iklan yang bisa mengedit. Sistem memverifikasi via contact_id dan nomor telepon. Master admin memiliki akses bypass</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Video Upload Support</strong> — Halaman edit iklan (admin & member) kini mendukung upload video MP4, WEBM, MOV hingga 20MB</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Video Thumbnail di Seluruh Platform</strong> — Iklan berformat video tampil sebagai player di halaman index, detail, dashboard member, dan profil seller</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>Media Storage Fix</strong> — Gateway Node.js kini memanggil <code>msg.downloadMedia()</code> dengan benar sehingga foto/video dari WhatsApp tersimpan ke server</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag ai"><i class="bi bi-stars me-1"></i>AI Edit</span>
                                <span class="rn-tag new"><i class="bi bi-camera-video me-1"></i>Video</span>
                                <span class="rn-tag fix"><i class="bi bi-bug me-1"></i>Bug Fix</span>
                                <span class="rn-tag api"><i class="bi bi-whatsapp me-1"></i>WhatsApp</span>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ v0.4.1 — CRITICAL: LID Reply Routing Fix ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot red"><i class="bi bi-shield-exclamation"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version stable">v0.4.1</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>9 Maret 2026</span>
                            </div>
                            <h4>🛡️ Critical Bug Fix: LID Reply Routing</h4>
                            <p class="rn-summary">
                                Perbaikan bug kritis — bot membalas pesan ke nomor LID internal WhatsApp (<code>91268138926223</code>) alih-alih nomor telepon nyata pengirim (<code>6285719195627</code>). Akibatnya semua balasan AI tidak pernah sampai ke user. Dua lapisan fix diterapkan: gateway resolusi LID dan filter PHP diperkuat.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>Gateway: LID Resolution</strong> — Ketika JID berakhiran <code>@lid</code> atau nomor pengirim memiliki 14+ digit (ciri LID), gateway sekarang memanggil <code>contact.id.user</code> via Baileys untuk mendapatkan nomor telepon nyata sebelum dikirim ke webhook</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>PHP: Simplified LID Filter</strong> — Filter lama menggunakan whitelist kode negara (62, 60, 91, dll.) — nomor LID <code>91268138926223</code> lolos karena awalan <code>91</code> cocok kode India. Filter baru: <em>semua nomor 14+ digit = LID</em>, tanpa pengecualian</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>Root Cause</strong> — WhatsApp mengubah format JID sebagian kontak ke sistem Linked ID (LID) internal. LID bukan nomor telepon nyata sehingga pesan tidak terkirim ke siapapun</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag fix" style="background:#e74c3c20;color:#e74c3c;border-color:#e74c3c40;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Critical Fix</span>
                                <span class="rn-tag fix"><i class="bi bi-bug me-1"></i>Bug Fix</span>
                                <span class="rn-tag api"><i class="bi bi-gateway me-1"></i>Gateway</span>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ v0.4 — AI Smart Clarify + Location Support ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot green"><i class="bi bi-stars"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version stable">v0.4</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>8 Maret 2026</span>
                            </div>
                            <h4>AI Smart Clarify & Location Support</h4>
                            <p class="rn-summary">
                                Bot AI sekarang jauh lebih pintar — mampu menerima dan memproses pesan lokasi dari WhatsApp, serta aktif menanyakan klarifikasi ketika pesan user tidak jelas. Tidak ada lagi asumsi yang salah.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>AI Clarify Intent</strong> — Ketika Gemini tidak pasti maksud pesan (misal kata tunggal "gamis", "kue"), bot otomatis menanyakan klarifikasi spesifik yang dihasilkan Gemini, bukan jawaban generik</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Clarify Context Carry</strong> — Jawaban klarifikasi user digabung dengan pesan original sebelum diproses ulang, sehingga konteks tidak hilang (cache 5 menit)</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Location Message Support</strong> — Bot sekarang bisa menerima pin lokasi dari WhatsApp dan menanyakan tujuannya: update lokasi bisnis atau cari produk di sekitar lokasi</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Update Lokasi Bisnis via WA</strong> — Member bisa kirim pin lokasi → pilih opsi 1 → sistem simpan koordinat GPS + reverse-geocode alamat otomatis ke profil kontak</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Location-Based Product Search</strong> — Setelah kirim lokasi → pilih opsi 2 → RAG search produk di sekitar area lokasi yang dikirim</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>Bug Fix: Location Silent Drop</strong> — Pesan tipe <code>location</code> sebelumnya langsung di-drop karena guard <code>empty($text)</code> berjalan sebelum cek tipe — urutan logic diperbaiki</span>
                                </li>
                                <li>
                                    <span class="ri-icon fix"><i class="bi bi-bug-fill"></i></span>
                                    <span><strong>DB Fix: messages_message_type_check</strong> — Constraint PostgreSQL diperbaiki agar tipe <code>location</code> diizinkan disimpan ke tabel <code>messages</code></span>
                                </li>
                                <li>
                                    <span class="ri-icon db"><i class="bi bi-database"></i></span>
                                    <span><strong>Schema: contacts.latitude/longitude/address</strong> — Kolom baru ditambahkan ke tabel <code>contacts</code> untuk menyimpan koordinat GPS dan alamat bisnis penjual</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag ai"><i class="bi bi-robot me-1"></i>AI</span>
                                <span class="rn-tag db"><i class="bi bi-database me-1"></i>DB Migration</span>
                                <span class="rn-tag fix"><i class="bi bi-bug me-1"></i>Bug Fix</span>
                                <span class="rn-tag api"><i class="bi bi-geo-alt me-1"></i>Location</span>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ v0.3 — RAG & Chatbot Intelligence ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot blue"><i class="bi bi-search-heart-fill"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version stable">v0.3</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>Februari 2026</span>
                            </div>
                            <h4>RAG Semantic Search & BotQueryAgent</h4>
                            <p class="rn-summary">
                                Implementasi penuh sistem Retrieval-Augmented Generation (RAG) untuk pencarian produk semantik via WhatsApp DM. Pembeli kini bisa cari produk dengan bahasa natural.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>RAG Search Engine</strong> — <code>ragRetrieveListings()</code>: ambil hingga 100 listing dari DB, Gemini reranking semantik, kembalikan subset paling relevan</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>BotQueryAgent</strong> — Agent khusus untuk menjawab query DM pembeli: cari produk, cari penjual, lihat kategori, iklan saya, tanya jawab umum</span>
                                </li>
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>Intent Parsing via Gemini</strong> — Setiap pesan DM dianalisis Gemini untuk menentukan intent: <code>search_product</code>, <code>search_seller</code>, <code>list_categories</code>, <code>my_listings</code>, <code>help</code>, <code>greeting</code>, <code>general_question</code>, <code>conversation</code></span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>My Listings Feature</strong> — Member bisa tanya "iklan apa yang saya punya?" dan bot menampilkan semua iklan aktif mereka dengan link</span>
                                </li>
                                <li>
                                    <span class="ri-icon impr"><i class="bi bi-arrow-up"></i></span>
                                    <span><strong>Conversational Context</strong> — Bot mampu menjawab pertanyaan umum tentang platform dan berinteraksi natural</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag ai"><i class="bi bi-robot me-1"></i>RAG</span>
                                <span class="rn-tag ai"><i class="bi bi-stars me-1"></i>Gemini AI</span>
                                <span class="rn-tag api"><i class="bi bi-chat-dots me-1"></i>Chatbot</span>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ v0.2 — Platform Core Features ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot amber"><i class="bi bi-building"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version stable">v0.2</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>Januari 2026</span>
                            </div>
                            <h4>Fitur Platform Lengkap</h4>
                            <p class="rn-summary">
                                Penambahan fitur-fitur utama platform: dashboard member, profil penjual publik, moderasi AI, multi-grup support, dan panel admin lengkap.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Member Dashboard</strong> — Login via OTP WhatsApp, kelola iklan sendiri, edit detail produk</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Seller Public Profile</strong> — Halaman profil publik <code>/u/{phone}</code> dengan semua iklan aktif penjual</span>
                                </li>
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>MessageModerationAgent</strong> — AI moderasi konten 24/7, filter produk SARA/tidak sesuai komunitas</span>
                                </li>
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>ImageAnalyzerAgent</strong> — Analisis foto produk untuk verifikasi dan deskripsi tambahan</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Multi-Grup Support</strong> — Satu platform bisa pantau banyak grup WhatsApp sekaligus</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Admin Panel</strong> — Dashboard lengkap: manajemen pesan, iklan, grup, kategori, template sistem, log AI</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>AI Health Monitor</strong> — Panel monitor performa semua agent AI beserta log eksekusi detail</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>MemberOnboardingAgent</strong> — Onboarding otomatis untuk anggota baru yang bergabung ke grup</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>BroadcastAgent</strong> — Kirim announcement ke seluruh anggota grup secara terjadwal</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>GroupAdminReplyAgent</strong> — Balas otomatis untuk moderasi dan pengumuman di grup komunitas</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag ui"><i class="bi bi-window me-1"></i>UI</span>
                                <span class="rn-tag ai"><i class="bi bi-robot me-1"></i>AI Agents</span>
                                <span class="rn-tag infra"><i class="bi bi-gear me-1"></i>Admin</span>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ v0.1 — Foundation ═══ --}}
                    <div class="rn-entry">
                        <div class="rn-dot purple"><i class="bi bi-rocket-takeoff-fill"></i></div>
                        <div class="rn-card">
                            <div class="rn-meta">
                                <span class="rn-version beta">v0.1</span>
                                <span class="rn-date"><i class="bi bi-calendar3 me-1"></i>Desember 2025</span>
                            </div>
                            <h4>Foundation — Core AI Pipeline</h4>
                            <p class="rn-summary">
                                Inisialisasi platform. Pipeline utama dari WhatsApp grup ke marketplace publik sudah berjalan end-to-end untuk pertama kalinya.
                            </p>
                            <ul class="rn-items">
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>WhatsApp Gateway</strong> — Integrasi Baileys (Node.js) sebagai WA gateway dengan endpoint API untuk komunikasi dua arah</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>WhatsAppListenerAgent</strong> — Penerimaan webhook, routing pesan, dan persistensi ke database</span>
                                </li>
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>AdClassifierAgent</strong> — Klasifikasi apakah pesan grup adalah iklan menggunakan Gemini AI</span>
                                </li>
                                <li>
                                    <span class="ri-icon ai"><i class="bi bi-stars"></i></span>
                                    <span><strong>DataExtractorAgent</strong> — Ekstraksi judul, harga, deskripsi, lokasi, kontak dari teks bebas menggunakan Gemini</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Public Marketplace Website</strong> — Halaman publik listing produk dengan filter kategori dan pencarian</span>
                                </li>
                                <li>
                                    <span class="ri-icon new"><i class="bi bi-plus-lg"></i></span>
                                    <span><strong>Async Queue Workers</strong> — Semua proses AI berjalan via Laravel Queue (Supervisor) agar webhook tetap cepat</span>
                                </li>
                                <li>
                                    <span class="ri-icon impr"><i class="bi bi-arrow-up"></i></span>
                                    <span><strong>Auto Category Classification</strong> — AI klasifikasikan iklan ke kategori yang tepat secara otomatis</span>
                                </li>
                                <li>
                                    <span class="ri-icon db"><i class="bi bi-database"></i></span>
                                    <span><strong>PostgreSQL Schema</strong> — Tables: contacts, listings, categories, messages, groups, system_messages</span>
                                </li>
                            </ul>
                            <div class="tag-row">
                                <span class="rn-tag infra"><i class="bi bi-server me-1"></i>Infrastructure</span>
                                <span class="rn-tag ai"><i class="bi bi-robot me-1"></i>AI Pipeline</span>
                                <span class="rn-tag db"><i class="bi bi-database me-1"></i>Database</span>
                                <span class="rn-tag api"><i class="bi bi-code-slash me-1"></i>API</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Upcoming ──────────────────────────────────────────── --}}
<section class="py-4 pb-5" style="background: linear-gradient(135deg,#f9fafb,#f0fdf4);">
    <div class="container">
        <div class="text-center mb-4">
            <div class="section-eyebrow">Roadmap</div>
            <div class="section-divider"></div>
            <h2 class="section-title">Fitur yang <span>Akan Datang</span></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 rounded-4 h-100" style="background:#fff;border:1.5px solid #e5e7eb;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span style="font-size:1.3rem;">📍</span>
                                <strong style="font-size:.88rem;">Lokasi Bisnis di Profil Penjual</strong>
                            </div>
                            <p style="font-size:.8rem;color:#6b7280;margin:0;">Tampilkan koordinat GPS + alamat bisnis penjual di halaman profil publik mereka — sudah tersimpan di DB, tinggal ditampilkan di UI</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-4 h-100" style="background:#fff;border:1.5px solid #e5e7eb;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span style="font-size:1.3rem;">💰</span>
                                <strong style="font-size:.88rem;">Filter Harga & Lokasi di Website</strong>
                            </div>
                            <p style="font-size:.8rem;color:#6b7280;margin:0;">Filter produk berdasarkan range harga dan jarak dari lokasi user di halaman publik</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded-4 h-100" style="background:#fff;border:1.5px solid #e5e7eb;">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span style="font-size:1.3rem;">📊</span>
                                <strong style="font-size:.88rem;">Analytics Dashboard Penjual</strong>
                            </div>
                            <p style="font-size:.8rem;color:#6b7280;margin:0;">Statistik views, klik WA, dan tren performa iklan per penjual di dashboard member</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── CTA ───────────────────────────────────────────────── --}}
<section class="py-5">
    <div class="container">
        <div class="p-4 p-md-5 rounded-4 text-center"
             style="background:linear-gradient(135deg,#042f24 0%,#064e3b 45%,#059669 100%);color:#fff;box-shadow:0 16px 60px rgba(5,150,105,.3);">
            <h3 style="font-size:1.6rem;font-weight:900;margin-bottom:.5rem;">Semua Fitur Ini <span style="color:#6ee7b7;">Gratis untuk Komunitas</span></h3>
            <p style="color:rgba(255,255,255,.7);font-size:.9rem;margin-bottom:1.5rem;">Posting iklan di grup WhatsApp seperti biasa &mdash; cukup chat, tanpa daftar, tanpa login</p>
            <div class="d-flex gap-3 justify-content-center flex-wrap">
                <a href="{{ url('/') }}"
                   style="background:#fff;color:#047857;border:none;border-radius:12px;padding:.7rem 1.8rem;font-size:.9rem;font-weight:800;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;box-shadow:0 6px 24px rgba(0,0,0,.2);">
                    <i class="bi bi-grid"></i> Lihat Semua Produk
                </a>
                <a href="{{ url('/marketing-tools') }}"
                   style="background:transparent;color:#fff;border:2px solid rgba(255,255,255,.5);border-radius:12px;padding:.68rem 1.6rem;font-size:.9rem;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;">
                    <i class="bi bi-grid-3x3-gap"></i> Lihat Semua Fitur
                </a>
            </div>
        </div>
    </div>
</section>

{{-- ── Footer ───────────────────────────────────────────── --}}
<footer class="site-footer">
    <div class="container">
        <div class="footer-logo">Marketplace<span>Jamaah</span> <span style="color:rgba(255,255,255,.4);font-weight:400;font-size:.85rem;"> AI</span></div>
        <p>{{ $__t('Platform marketplace komunitas WhatsApp berbasis AI','AI-powered WhatsApp community marketplace') }} &bull; <a href="{{ url('/') }}">{{ $__t('Lihat Produk','View Products') }}</a> &bull; <a href="{{ url('/fitur') }}">{{ $__t('Fitur & Cara Kerja','Features & How It Works') }}</a> &bull; <a href="{{ url('/release-notes') }}">Release Notes</a></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
