<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fitur & Cara Kerja — MarketplaceJamaah AI</title>
    <meta name="description" content="Panduan lengkap fitur-fitur MarketplaceJamaah AI: marketplace otomatis berbasis WhatsApp, AI search, manajemen iklan, dan workflow dari grup ke pembeli.">
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
        body { background:#f0fdf8; color:#111827; font-family:'Plus Jakarta Sans','Segoe UI',sans-serif; }

        /* ── Navbar ─────────────────── */
        .site-nav {
            background:rgba(255,255,255,.92); backdrop-filter:blur(12px);
            border-bottom:1.5px solid rgba(167,243,208,.5);
            padding:.7rem 0; position:sticky; top:0; z-index:100;
            box-shadow:0 2px 16px rgba(5,150,105,.1);
        }
        .brand-logo {
            width:40px; height:40px; border-radius:12px;
            background:linear-gradient(135deg,#059669 0%,#10b981 50%,#34d399 100%);
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 4px 12px rgba(5,150,105,.45);
            flex-shrink:0;
        }
        .brand-logo i { color:#fff; font-size:1.15rem; }
        .brand-name { font-size:1.1rem; font-weight:800; color:#111827; line-height:1.2; }
        .brand-name span { color:var(--em); }
        .brand-sub { font-size:.65rem; color:#6b7280; font-weight:500; }
        .btn-nav-primary {
            background:linear-gradient(135deg,#059669,#10b981,#34d399);
            color:#fff; border:none; border-radius:10px;
            font-size:.82rem; font-weight:700; padding:.48rem 1.2rem;
            text-decoration:none; display:inline-flex; align-items:center; gap:.35rem;
            box-shadow:0 3px 12px rgba(5,150,105,.45); transition:all .2s;
        }
        .btn-nav-primary:hover { color:#fff; transform:translateY(-2px); box-shadow:0 6px 20px rgba(5,150,105,.5); }
        .btn-nav-outline {
            background:var(--em-xlight); color:var(--em-dark); border:1.5px solid var(--em-light);
            border-radius:10px; font-size:.82rem; font-weight:700; padding:.42rem 1.1rem;
            text-decoration:none; display:inline-flex; align-items:center; gap:.38rem;
            transition:all .18s;
        }
        .btn-nav-outline:hover { background:var(--em); color:#fff; border-color:var(--em); }

        /* ── Page Hero ───────────────── */
        .page-hero {
            background:linear-gradient(135deg,#042f24 0%,#064e3b 45%,#065f46 100%);
            padding:5rem 0 6rem;
            position:relative; overflow:hidden;
        }
        .page-hero::before {
            content:''; position:absolute; top:-60px; right:-80px;
            width:500px; height:500px; border-radius:50%;
            background:radial-gradient(circle, rgba(5,150,105,.35) 0%, transparent 70%);
        }
        .page-hero::after {
            content:''; position:absolute; bottom:-100px; left:-60px;
            width:400px; height:400px; border-radius:50%;
            background:radial-gradient(circle, rgba(52,211,153,.2) 0%, transparent 70%);
        }
        .page-hero .eyebrow {
            display:inline-flex; align-items:center; gap:.45rem;
            background:rgba(110,231,183,.12); border:1.5px solid rgba(110,231,183,.4);
            color:#6ee7b7; border-radius:100px; padding:.3rem 1.05rem;
            font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
            margin-bottom:1.25rem;
        }
        .page-hero h1 {
            font-size:3.2rem; font-weight:900; color:#fff; line-height:1.15;
            margin-bottom:1rem;
        }
        .page-hero h1 em { color:var(--em-mid); font-style:normal; }
        .page-hero .lead { font-size:1.1rem; color:rgba(255,255,255,.75); max-width:640px; line-height:1.7; }
        .hero-stat-row { display:flex; flex-wrap:wrap; gap:1rem; margin-top:2rem; }
        .hero-stat {
            background:rgba(255,255,255,.08); border:1.5px solid rgba(255,255,255,.15);
            border-radius:14px; padding:.75rem 1.25rem; text-align:center; min-width:110px;
        }
        .hero-stat .val { font-size:1.7rem; font-weight:900; color:#6ee7b7; line-height:1; }
        .hero-stat .lbl { font-size:.7rem; color:rgba(255,255,255,.6); font-weight:600; margin-top:.2rem; }

        /* ── Section styles ──────────── */
        .section-divider {
            height:3px; width:60px; border-radius:4px;
            background:linear-gradient(90deg,var(--em),var(--em-mid));
            margin:0 auto .75rem;
        }
        .section-eyebrow {
            font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em;
            color:var(--em); margin-bottom:.35rem;
        }
        .section-title {
            font-size:2.1rem; font-weight:900; color:#111827; line-height:1.2;
        }
        .section-title span { color:var(--em); }
        .section-sub { font-size:.95rem; color:#6b7280; max-width:560px; margin:0 auto; line-height:1.7; }

        /* ── Feature cards ───────────── */
        .feat-card {
            background:#fff; border:1.5px solid #e5e7eb; border-radius:20px;
            padding:1.75rem 1.6rem; height:100%;
            transition:transform .25s, box-shadow .25s, border-color .25s;
            box-shadow:0 2px 10px rgba(0,0,0,.05);
        }
        .feat-card:hover {
            transform:translateY(-6px);
            box-shadow:0 20px 50px rgba(5,150,105,.15), 0 4px 16px rgba(0,0,0,.07);
            border-color:var(--em-mid);
        }
        .feat-icon {
            width:56px; height:56px; border-radius:16px;
            display:flex; align-items:center; justify-content:center;
            font-size:1.5rem; margin-bottom:1rem; flex-shrink:0;
        }
        .feat-icon.green  { background:linear-gradient(135deg,var(--em-xlight),#a7f3d0); color:var(--em-dark); }
        .feat-icon.amber  { background:linear-gradient(135deg,var(--amber-light),#fcd34d); color:#92400e; }
        .feat-icon.blue   { background:linear-gradient(135deg,var(--blue-light),#bfdbfe); color:#1d4ed8; }
        .feat-icon.purple { background:linear-gradient(135deg,var(--purple-light),#ddd6fe); color:#6d28d9; }
        .feat-icon.rose   { background:linear-gradient(135deg,var(--rose-light),#fecdd3); color:#be123c; }
        .feat-card h5 { font-size:1.02rem; font-weight:800; color:#111827; margin-bottom:.55rem; }
        .feat-card p { font-size:.85rem; color:#6b7280; line-height:1.7; margin:0; }
        .feat-tag {
            display:inline-block; padding:.18rem .65rem; border-radius:100px;
            font-size:.68rem; font-weight:700; margin-top:.9rem;
        }
        .feat-tag.ai   { background:var(--em-xlight); color:var(--em-dark); border:1px solid var(--em-light); }
        .feat-tag.auto { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
        .feat-tag.free { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }

        /* ── Workflow ────────────────── */
        .workflow-section { background:linear-gradient(135deg,#f0fdf8,#f0f9ff); }
        .workflow-step {
            background:#fff; border:1.5px solid #e5e7eb; border-radius:20px;
            padding:1.6rem 1.5rem; position:relative; height:100%;
            box-shadow:0 2px 10px rgba(0,0,0,.04);
            transition:transform .2s, box-shadow .2s;
        }
        .workflow-step:hover { transform:translateY(-4px); box-shadow:0 12px 40px rgba(5,150,105,.12); }
        .step-num {
            width:44px; height:44px; border-radius:50%;
            background:linear-gradient(135deg,#059669,#34d399);
            display:flex; align-items:center; justify-content:center;
            font-size:1.05rem; font-weight:900; color:#fff;
            box-shadow:0 4px 14px rgba(5,150,105,.45);
            margin-bottom:1rem; flex-shrink:0;
        }
        .workflow-step h6 { font-size:.95rem; font-weight:800; color:#111827; margin-bottom:.4rem; }
        .workflow-step p { font-size:.82rem; color:#6b7280; margin:0; line-height:1.65; }
        .step-actor {
            font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
            padding:.18rem .6rem; border-radius:100px; display:inline-block; margin-bottom:.65rem;
        }
        .actor-admin   { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
        .actor-member  { background:var(--em-xlight); color:var(--em-dark); border:1px solid var(--em-light); }
        .actor-ai      { background:#f5f3ff; color:#6d28d9; border:1px solid #ddd6fe; }
        .actor-buyer   { background:#fff1f2; color:#be123c; border:1px solid #fecdd3; }
        .actor-system  { background:#f9fafb; color:#374151; border:1px solid #e5e7eb; }

        /* connector arrow */
        .workflow-connector {
            display:flex; align-items:center; justify-content:center;
            color:#d1d5db; font-size:1.5rem; padding:.25rem 0;
        }
        @media(min-width:768px) {
            .workflow-connector { display:none; }
        }

        /* ── Architecture diagram ────── */
        .arch-box {
            background:#fff; border:2px solid #e5e7eb; border-radius:16px;
            padding:1.2rem 1.4rem; text-align:center;
            box-shadow:0 2px 8px rgba(0,0,0,.05);
        }
        .arch-box.highlight {
            border-color:var(--em); background:var(--em-xlight);
            box-shadow:0 4px 20px rgba(5,150,105,.18);
        }
        .arch-box .arch-icon { font-size:2.2rem; margin-bottom:.45rem; }
        .arch-box .arch-label { font-size:.82rem; font-weight:700; color:#374151; }
        .arch-box .arch-sub { font-size:.7rem; color:#9ca3af; margin-top:.2rem; }
        .arch-arrow {
            display:flex; align-items:center; justify-content:center;
            color:#6b7280; font-size:1.4rem;
        }

        /* ── Comparison table ────────── */
        .comp-table { border-radius:16px; overflow:hidden; border:1.5px solid #e5e7eb; }
        .comp-table thead { background:linear-gradient(135deg,#059669,#047857); color:#fff; }
        .comp-table thead th { font-weight:700; font-size:.85rem; padding:1rem 1.1rem; border:none; }
        .comp-table tbody tr { border-color:#f3f4f6; }
        .comp-table tbody td { font-size:.85rem; padding:.85rem 1.1rem; vertical-align:middle; }
        .comp-table tbody tr:hover { background:var(--em-xlight); }
        .check-yes { color:var(--em); font-size:1.1rem; font-weight:700; }
        .check-no  { color:#d1d5db; font-size:1.1rem; }

        /* ── FAQ ─────────────────────── */
        .faq-item {
            background:#fff; border:1.5px solid #e5e7eb; border-radius:16px;
            margin-bottom:.75rem; overflow:hidden;
            transition:border-color .2s;
        }
        .faq-item:hover { border-color:var(--em-mid); }
        .faq-btn {
            width:100%; text-align:left; background:none; border:none; padding:1.1rem 1.4rem;
            font-size:.92rem; font-weight:700; color:#111827; cursor:pointer;
            display:flex; align-items:center; justify-content:space-between; gap:1rem;
        }
        .faq-btn .faq-icon { color:var(--em); transition:transform .25s; font-size:1rem; flex-shrink:0; }
        .faq-btn[aria-expanded="true"] .faq-icon { transform:rotate(180deg); }
        .faq-body { padding:0 1.4rem 1.1rem; font-size:.85rem; color:#6b7280; line-height:1.75; }

        /* ── CTA strip ───────────────── */
        .cta-strip {
            background:linear-gradient(135deg,#042f24 0%,#064e3b 45%,#059669 100%);
            border-radius:24px; padding:3.5rem 2.5rem;
            text-align:center; color:#fff;
            box-shadow:0 16px 60px rgba(5,150,105,.3);
            position:relative; overflow:hidden;
        }
        .cta-strip::before {
            content:''; position:absolute; top:-40px; right:-40px;
            width:280px; height:280px; border-radius:50%;
            background:radial-gradient(circle,rgba(52,211,153,.25) 0%,transparent 70%);
        }
        .cta-strip h2 { font-size:2rem; font-weight:900; margin-bottom:.5rem; }
        .cta-strip p  { color:rgba(255,255,255,.7); font-size:.95rem; margin-bottom:2rem; }
        .btn-cta-white {
            background:#fff; color:var(--em-dark); border:none;
            border-radius:12px; padding:.75rem 2rem; font-size:.92rem; font-weight:800;
            text-decoration:none; display:inline-flex; align-items:center; gap:.5rem;
            box-shadow:0 6px 24px rgba(0,0,0,.2);
            transition:all .2s;
        }
        .btn-cta-white:hover { transform:translateY(-3px); box-shadow:0 10px 32px rgba(0,0,0,.25); color:var(--em); }
        .btn-cta-outline {
            background:transparent; color:#fff; border:2px solid rgba(255,255,255,.5);
            border-radius:12px; padding:.73rem 1.8rem; font-size:.92rem; font-weight:700;
            text-decoration:none; display:inline-flex; align-items:center; gap:.5rem;
            transition:all .2s;
        }
        .btn-cta-outline:hover { background:rgba(255,255,255,.1); color:#fff; border-color:#fff; }

        /* ── Footer ──────────────────── */
        .site-footer {
            background:linear-gradient(135deg,#064e3b,#065f46);
            padding:2.5rem 0; text-align:center; margin-top:5rem;
        }
        .site-footer .footer-logo { font-size:1.05rem; font-weight:800; color:#fff; }
        .site-footer .footer-logo span { color:#6ee7b7; }
        .site-footer p { font-size:.8rem; color:rgba(255,255,255,.5); margin:.3rem 0 0; }
        .site-footer a { color:#6ee7b7; text-decoration:none; font-weight:600; }

        /* ── Responsive ──────────────── */
        @media(max-width:768px) {
            .page-hero h1 { font-size:2.1rem; }
            .page-hero .lead { font-size:.92rem; }
            .section-title { font-size:1.6rem; }
            .cta-strip { padding:2.5rem 1.25rem; }
            .cta-strip h2 { font-size:1.5rem; }
        }
    </style>
</head>
<body>

{{-- ── Navbar ────────────────────────────────────────── --}}
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
            <a href="{{ url('/') }}" class="btn-nav-outline d-none d-sm-flex">
                <i class="bi bi-grid"></i> Lihat Produk
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-nav-primary">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            @endauth
        </div>
    </div>
</nav>

{{-- ── Page Hero ──────────────────────────────────────── --}}
<section class="page-hero">
    <div class="container position-relative" style="z-index:2;">
        <div class="eyebrow"><i class="bi bi-stars"></i>&nbsp;Fitur Lengkap &bull; Workflow</div>
        <h1>Semua yang Kamu Butuhkan<br>untuk <em>Marketplace Komunitas</em></h1>
        <p class="lead">
            MarketplaceJamaah AI mengubah obrolan jual-beli di grup WhatsApp menjadi marketplace profesional secara otomatis — tanpa coding, tanpa ribet.
        </p>
        <div class="hero-stat-row">
            <div class="hero-stat">
                <div class="val">{{ $totalActive }}</div>
                <div class="lbl">Iklan Aktif</div>
            </div>
            <div class="hero-stat">
                <div class="val">{{ $totalSellers }}</div>
                <div class="lbl">Penjual</div>
            </div>
            <div class="hero-stat">
                <div class="val">{{ $totalCategories }}</div>
                <div class="lbl">Kategori</div>
            </div>
            <div class="hero-stat">
                <div class="val">AI</div>
                <div class="lbl">Powered</div>
            </div>
        </div>
    </div>
</section>

{{-- ── Overview ───────────────────────────────────────── --}}
<section class="py-5 mt-n3">
    <div class="container">
        {{-- Intro benefit strip --}}
        <div class="row g-3 justify-content-center mb-5">
            <div class="col-12">
                <div class="p-4 rounded-4" style="background:#fff;border:1.5px solid #e5e7eb;box-shadow:0 4px 20px rgba(0,0,0,.05);">
                    <div class="row g-3 text-center text-md-start align-items-center">
                        <div class="col-md-4 border-end-md" style="border-right:0;">
                            <div class="d-flex align-items-center gap-3">
                                <div class="feat-icon green flex-shrink-0"><i class="bi bi-robot"></i></div>
                                <div>
                                    <div class="fw-800 text-dark" style="font-weight:800;">100% Otomatis</div>
                                    <div class="text-secondary" style="font-size:.82rem;">AI membaca chat grup, deteksi iklan, ekstrak data — tanpa input manual</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="feat-icon amber flex-shrink-0"><i class="bi bi-whatsapp"></i></div>
                                <div>
                                    <div class="fw-800 text-dark" style="font-weight:800;">Native WhatsApp</div>
                                    <div class="text-secondary" style="font-size:.82rem;">Penjual posting di grup WA seperti biasa — tidak perlu belajar platform baru</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="feat-icon blue flex-shrink-0"><i class="bi bi-search-heart"></i></div>
                                <div>
                                    <div class="fw-800 text-dark" style="font-weight:800;">Pembeli Mudah Cari</div>
                                    <div class="text-secondary" style="font-size:.82rem;">Website publik + chatbot WA dengan semantic search berbasis RAG</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Features ───────────────────────────────────────── --}}
<section class="py-2 pb-5" id="fitur">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">Fitur Platform</div>
            <div class="section-divider"></div>
            <h2 class="section-title">Satu Platform, <span>Banyak Kemudahan</span></h2>
            <p class="section-sub mt-2">Setiap fitur dirancang agar komunitas jamaah bisa jual beli dengan cara paling natural — lewat WhatsApp yang sudah mereka gunakan setiap hari.</p>
        </div>

        <div class="row g-4">

            {{-- 1. Auto AI Listing Detection --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon green"><i class="bi bi-cpu-fill"></i></div>
                    <h5>Deteksi Iklan Otomatis dari Grup WA</h5>
                    <p>AI memantau setiap pesan di grup WhatsApp yang terdaftar. Begitu ada pesan jualan, AI otomatis mengenali — lalu mengekstrak judul, harga, deskripsi, lokasi, dan gambar produk tanpa intervensi admin.</p>
                    <span class="feat-tag ai"><i class="bi bi-robot me-1"></i>AI Powered</span>
                </div>
            </div>

            {{-- 2. Data Extraction --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon amber"><i class="bi bi-magic"></i></div>
                    <h5>Ekstraksi Data Cerdas</h5>
                    <p>Dari teks bebas seperti "Jual gamis polos Rp180rb, uk M-L-XL, hub wa inti", AI mengekstrak nama produk, harga, ukuran, kontak, dan kategori secara akurat menggunakan Google Gemini.</p>
                    <span class="feat-tag ai"><i class="bi bi-stars me-1"></i>Gemini AI</span>
                </div>
            </div>

            {{-- 3. Public Marketplace --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon blue"><i class="bi bi-shop-window"></i></div>
                    <h5>Website Marketplace Publik</h5>
                    <p>Semua iklan yang lolos moderasi tampil di halaman publik yang bisa diakses siapa saja — dengan filter kategori, pencarian teks, dan halaman detail produk setiap iklan.</p>
                    <span class="feat-tag free"><i class="bi bi-globe me-1"></i>Public Access</span>
                </div>
            </div>

            {{-- 4. RAG Semantic Search --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon purple"><i class="bi bi-search-heart-fill"></i></div>
                    <h5>Pencarian Semantik via Chat WA</h5>
                    <p>Pembeli cukup kirim pesan ke nomor WA bot seperti "cari gamis biru ukuran L sekitar Depok". AI menggunakan RAG (Retrieval-Augmented Generation) untuk memahami maksud pencarian dan menampilkan produk paling relevan — bukan sekedar keyword match.</p>
                    <span class="feat-tag ai"><i class="bi bi-robot me-1"></i>RAG Search</span>
                </div>
            </div>

            {{-- 5. AI Moderation --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon rose"><i class="bi bi-shield-check-fill"></i></div>
                    <h5>Moderasi Konten AI</h5>
                    <p>Setiap iklan yang dideteksi akan diperiksa kelayakannya sebelum tayang. AI memastikan konten sesuai nilai komunitas jamaah, tidak ada produk SARA, judi, atau tidak pantas.</p>
                    <span class="feat-tag auto"><i class="bi bi-check2-circle me-1"></i>Auto Moderate</span>
                </div>
            </div>

            {{-- 6. Category Management --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon green"><i class="bi bi-tags-fill"></i></div>
                    <h5>Klasifikasi Kategori Otomatis</h5>
                    <p>AI mengklasifikasikan setiap iklan ke kategori yang tepat (Pakaian, Makanan, Elektronik, Properti, dll) secara otomatis. Admin bisa kelola kategori sesuai kebutuhan komunitas.</p>
                    <span class="feat-tag ai"><i class="bi bi-diagram-3 me-1"></i>Auto Classify</span>
                </div>
            </div>

            {{-- 7. Kelola Iklan 100% via Bot --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon amber"><i class="bi bi-robot"></i></div>
                    <h5>Kelola Iklan 100% via Bot WhatsApp</h5>
                    <p>Tidak perlu login ke website. Semua bisa dilakukan dari chat bot: lihat daftar iklan (<strong>iklan saya</strong>), edit detail (<strong>edit #ID</strong>), dan cek status. Cukup chat, selesai.</p>
                    <span class="feat-tag ai"><i class="bi bi-whatsapp me-1"></i>Zero Login</span>
                </div>
            </div>

            {{-- 8. Seller Profile --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon blue"><i class="bi bi-person-badge-fill"></i></div>
                    <h5>Halaman Profil Penjual</h5>
                    <p>Setiap penjual punya halaman profil publik yang menampilkan semua iklan aktif mereka. Ideal untuk membangun reputasi dan kepercayaan pembeli di komunitas.</p>
                    <span class="feat-tag free"><i class="bi bi-link-45deg me-1"></i>Public Page</span>
                </div>
            </div>

            {{-- 9. Multi Group --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon purple"><i class="bi bi-people-fill"></i></div>
                    <h5>Multi-Grup WhatsApp</h5>
                    <p>Satu platform bisa memantau banyak grup WhatsApp sekaligus — grup bukalapak komunitas, grup arisan, grup masjid, dan lainnya. Semua iklan terkumpul di satu marketplace.</p>
                    <span class="feat-tag auto"><i class="bi bi-collection me-1"></i>Multi Group</span>
                </div>
            </div>

            {{-- 10. AI Chatbot Conversational --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon rose"><i class="bi bi-chat-dots-fill"></i></div>
                    <h5>Chatbot AI Interaktif di WA</h5>
                    <p>Pengguna bisa ngobrol natural dengan bot via WhatsApp personal. Bot memahami konteks, menjawab pertanyaan tentang marketplace, membantu cari produk, dan memberi info kategori yang tersedia.</p>
                    <span class="feat-tag ai"><i class="bi bi-robot me-1"></i>Conversational AI</span>
                </div>
            </div>

            {{-- 11. Admin Panel --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon green"><i class="bi bi-speedometer2"></i></div>
                    <h5>Panel Admin Lengkap</h5>
                    <p>Admin punya dashboard penuh untuk memantau semua pesan masuk, manajemen iklan, kontrol grup, pengaturan kategori, template pesan sistem, dan log aktivitas AI.</p>
                    <span class="feat-tag free"><i class="bi bi-gear me-1"></i>Full Control</span>
                </div>
            </div>

            {{-- 12. AI Health Monitor --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon amber"><i class="bi bi-activity"></i></div>
                    <h5>Monitor Kesehatan AI</h5>
                    <p>Panel khusus untuk memantau performa semua agent AI yang berjalan — mulai dari classifier, extractor, moderator, hingga chatbot. Bisa melihat log detail setiap eksekusi AI.</p>
                    <span class="feat-tag auto"><i class="bi bi-graph-up me-1"></i>AI Monitoring</span>
                </div>
            </div>

            {{-- 13. AI Smart Clarify --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon purple"><i class="bi bi-chat-quote-fill"></i></div>
                    <h5>AI Tanya Balik Saat Tidak Yakin</h5>
                    <p>Bot tidak asal tebak ketika pesan tidak jelas. Jika Gemini tidak yakin maksud user (misal hanya menulis "gamis" atau "kue"), bot menanyakan klarifikasi spesifik — pertanyaannya dihasilkan AI sesuai konteks. Jawaban user digabung dengan pesan awal secara otomatis.</p>
                    <span class="feat-tag ai"><i class="bi bi-stars me-1"></i>Smart Clarify</span>
                </div>
            </div>

            {{-- 14. Location Message Support --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon green"><i class="bi bi-geo-alt-fill"></i></div>
                    <h5>Kirim Lokasi via WhatsApp</h5>
                    <p>Member bisa kirim pin lokasi dari WhatsApp ke bot. Bot bertanya: mau <strong>update lokasi bisnis</strong> ke profil, atau <strong>cari produk di sekitar area</strong> itu? Koordinat GPS disimpan dan alamat di-reverse-geocode otomatis via AI.</p>
                    <span class="feat-tag ai"><i class="bi bi-geo me-1"></i>Location AI</span>
                </div>
            </div>

            {{-- 15. Edit Iklan via DM --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon purple"><i class="bi bi-pencil-square"></i></div>
                    <h5>Edit Iklan via Chat WhatsApp</h5>
                    <p>Member cukup ketik <strong>edit #ID</strong> di chat pribadi bot, lalu kirim perubahan dalam bahasa bebas. AI Gemini menerjemahkan instruksi ke field yang tepat — judul, harga, deskripsi, stok. Dilengkapi <strong>proteksi kepemilikan</strong> agar hanya pemilik yang bisa edit.</p>
                    <span class="feat-tag ai"><i class="bi bi-stars me-1"></i>AI Edit</span>
                    <span class="feat-tag wa"><i class="bi bi-whatsapp me-1"></i>WhatsApp DM</span>
                </div>
            </div>

            {{-- 16. Landing Page Hero Slider --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon green"><i class="bi bi-layout-wtf"></i></div>
                    <h5>Landing Page dengan Hero Slider</h5>
                    <p>Halaman publik marketplace tampil profesional dengan <strong>hero slider</strong> berisi iklan foto/video terbaik, bagian <strong>iklan barus</strong> (tanpa foto), filter kategori, pencarian real-time, dan pagination — semua tanpa perlu login.</p>
                    <span class="feat-tag free"><i class="bi bi-globe me-1"></i>Public</span>
                    <span class="feat-tag auto"><i class="bi bi-images me-1"></i>Hero Slider</span>
                </div>
            </div>

            {{-- 17. Configurable Display Settings --}}
            <div class="col-md-6 col-lg-4">
                <div class="feat-card">
                    <div class="feat-icon amber"><i class="bi bi-sliders"></i></div>
                    <h5>Konfigurasi Tampilan Landing dari Admin</h5>
                    <p>Admin bisa mengatur jumlah iklan dengan media dan iklan barus yang ditampilkan di landing page langsung dari halaman <strong>Settings</strong> — tanpa perlu ubah kode. Perubahan langsung berlaku real-time tanpa deploy ulang.</p>
                    <span class="feat-tag free"><i class="bi bi-gear me-1"></i>Admin Control</span>
                    <span class="feat-tag ai"><i class="bi bi-toggles me-1"></i>Configurable</span>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ── Workflow ────────────────────────────────────────── --}}
<section class="workflow-section py-5 mt-2" id="workflow">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">Cara Kerja</div>
            <div class="section-divider"></div>
            <h2 class="section-title">Workflow dari <span>Grup WA ke Pembeli</span></h2>
            <p class="section-sub mt-2">Semua proses berjalan otomatis di balik layar. Penjual hanya perlu posting di grup seperti biasanya.</p>
        </div>

        <div class="row g-3">

            {{-- Step 1 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-admin">Admin</span>
                    <div class="step-num">1</div>
                    <h6>Setup Grup & Nomor WA</h6>
                    <p>Admin mendaftarkan nomor WhatsApp bisnis ke sistem dan menambahkan grup-grup WA komunitas yang ingin dipantau. Nomor WA yang sama juga berfungsi sebagai chatbot untuk pembeli.</p>
                </div>
            </div>

            {{-- Step 2 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-member">Penjual / Member</span>
                    <div class="step-num">2</div>
                    <h6>Posting Iklan di Grup WA</h6>
                    <p>Anggota komunitas posting pesan jualan di grup WhatsApp seperti biasa — bisa berupa teks, gambar, video, atau kombinasi. Tidak perlu format khusus, AI yang akan menganalisis.</p>
                </div>
            </div>

            {{-- Step 3 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-ai">AI Agent</span>
                    <div class="step-num">3</div>
                    <h6>AI Mendeteksi & Mengekstrak</h6>
                    <p>WhatsAppListenerAgent menerima pesan masuk. AdClassifierAgent menentukan apakah itu iklan. Jika ya, DataExtractorAgent mengekstrak semua detail produk dari teks bebas menggunakan Gemini AI.</p>
                </div>
            </div>

            {{-- Step 4 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-ai">AI Agent</span>
                    <div class="step-num">4</div>
                    <h6>Moderasi & Klasifikasi</h6>
                    <p>MessageModerationAgent memeriksa kesesuaian konten. ImageAnalyzerAgent menganalisis foto produk. Iklan yang lolos dikategorikan otomatis oleh AI dan disimpan ke database dengan status pending/active.</p>
                </div>
            </div>

            {{-- Step 5 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-system">Sistem</span>
                    <div class="step-num">5</div>
                    <h6>Iklan Tayang di Marketplace</h6>
                    <p>Iklan yang disetujui otomatis muncul di website marketplace publik. Setiap iklan punya halaman detail dengan foto, deskripsi, harga, dan tombol WhatsApp langsung ke penjual.</p>
                </div>
            </div>

            {{-- Step 6 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-buyer">Pembeli</span>
                    <div class="step-num">6</div>
                    <h6>Pembeli Menemukan Produk</h6>
                    <p>Pembeli bisa mencari produk lewat dua cara: <strong>website marketplace</strong> dengan filter kategori dan pencarian teks, atau <strong>chat whatsapp bot</strong> dengan bahasa natural seperti "ada jual hijab syari di Bekasi?"</p>
                </div>
            </div>

            {{-- Step 7 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-ai">AI Bot</span>
                    <div class="step-num">7</div>
                    <h6>AI Bot Menjawab & Memberi Link</h6>
                    <p>BotQueryAgent memproses pertanyaan pembeli menggunakan RAG — mencari iklan yang paling relevan secara semantik, lalu menjawab dengan ringkasan produk beserta link halaman detail dan info penjual.</p>
                </div>
            </div>

            {{-- Step 8 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-buyer">Pembeli</span>
                    <div class="step-num">8</div>
                    <h6>Klik, Hubungi, Transaksi</h6>
                    <p>Pembeli membuka link halaman detail produk, melihat foto dan informasi lengkap, lalu klik tombol WhatsApp untuk langsung chat dengan penjual. Transaksi berlangsung langsung antar pihak — platform hanya memfasilitasi pertemuan.</p>
                </div>
            </div>

            {{-- Step 9 --}}
            <div class="col-md-6 col-lg-4">
                <div class="workflow-step">
                    <span class="step-actor actor-member">Penjual / Member</span>
                    <div class="step-num">9</div>
                    <h6>Kelola Iklan via Chat Bot</h6>
                    <p>Penjual cukup chat bot untuk melihat semua iklan (<strong>iklan saya</strong>), mengedit detail produk (<strong>edit #ID</strong>), mengubah harga, deskripsi — semua dari WhatsApp tanpa perlu login website.</p>
                </div>
            </div>

        </div>
    </div>
</section>

{{-- ── Architecture ────────────────────────────────────── --}}
<section class="py-5" id="arsitektur">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">Arsitektur Sistem</div>
            <div class="section-divider"></div>
            <h2 class="section-title">Bagaimana <span>Sistem Bekerja</span></h2>
            <p class="section-sub mt-2">Gambaran arsitektur teknis platform — dari WhatsApp hingga marketplace website.</p>
        </div>

        <div class="row g-3 align-items-center justify-content-center">

            {{-- Row 1: Input --}}
            <div class="col-6 col-md-2">
                <div class="arch-box">
                    <div class="arch-icon">💬</div>
                    <div class="arch-label">Grup WA</div>
                    <div class="arch-sub">Pesan penjual</div>
                </div>
            </div>
            <div class="col-1 d-none d-md-flex arch-arrow"><i class="bi bi-arrow-right"></i></div>
            <div class="col-6 col-md-2">
                <div class="arch-box">
                    <div class="arch-icon">🔗</div>
                    <div class="arch-label">WA Gateway</div>
                    <div class="arch-sub">Baileys / integrasi-wa</div>
                </div>
            </div>
            <div class="col-1 d-none d-md-flex arch-arrow"><i class="bi bi-arrow-right"></i></div>
            <div class="col-6 col-md-2">
                <div class="arch-box highlight">
                    <div class="arch-icon">🤖</div>
                    <div class="arch-label">AI Agents</div>
                    <div class="arch-sub">Classify → Extract → Moderate</div>
                </div>
            </div>
            <div class="col-1 d-none d-md-flex arch-arrow"><i class="bi bi-arrow-right"></i></div>
            <div class="col-6 col-md-2">
                <div class="arch-box">
                    <div class="arch-icon">🗄️</div>
                    <div class="arch-label">Database</div>
                    <div class="arch-sub">PostgreSQL</div>
                </div>
            </div>

        </div>

        <div class="row g-3 justify-content-center mt-2">
            <div class="col-md-10">
                <div class="p-4 rounded-4" style="background:#fff;border:1.5px solid #e5e7eb;box-shadow:0 2px 12px rgba(0,0,0,.05);">
                    <div class="row g-4 align-items-center text-center">
                        <div class="col-6 col-md-3">
                            <div class="arch-box">
                                <div class="arch-icon">🌐</div>
                                <div class="arch-label">Website Publik</div>
                                <div class="arch-sub">marketplacejamaah-ai.jodyaryono.id</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="arch-box">
                                <div class="arch-icon">🤳</div>
                                <div class="arch-label">WA Chatbot</div>
                                <div class="arch-sub">Semantic DM search</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="arch-box">
                                <div class="arch-icon">🤖</div>
                                <div class="arch-label">Bot WhatsApp</div>
                                <div class="arch-sub">Kelola iklan via chat</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="arch-box highlight">
                                <div class="arch-icon">⚡</div>
                                <div class="arch-label">Queue Workers</div>
                                <div class="arch-sub">Async job processing</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3" style="font-size:.8rem;color:#9ca3af;">
                        <i class="bi bi-info-circle me-1"></i>
                        Semua proses AI berjalan asinkron via queue — memastikan respons WhatsApp tetap cepat meski volume pesan tinggi.
                    </div>
                </div>
            </div>
        </div>

        {{-- AI Agents breakdown --}}
        <div class="row g-3 justify-content-center mt-2">
            <div class="col-md-10">
                <h5 class="fw-800 text-dark mb-3" style="font-weight:800;font-size:.95rem;">
                    <i class="bi bi-diagram-3 me-2" style="color:var(--em);"></i>AI Agents yang Berjalan di Sistem
                </h5>
                <div class="row g-2">
                    @php
                        $agents = [
                            ['WhatsAppListenerAgent',  'bi-broadcast',       'Penerimaan & routing pesan masuk dari WA Gateway',              'green'],
                            ['AdClassifierAgent',      'bi-funnel-fill',     'Klasifikasi apakah pesan adalah iklan atau bukan',              'amber'],
                            ['DataExtractorAgent',     'bi-lightning-charge','Ekstraksi data produk dari teks bebas (judul, harga, dll)',     'blue'],
                            ['ImageAnalyzerAgent',     'bi-image-fill',      'Analisis gambar produk untuk verifikasi dan deskripsi',         'purple'],
                            ['MessageModerationAgent', 'bi-shield-check',    'Moderasi konten — memastikan iklan sesuai nilai komunitas',     'rose'],
                            ['BotQueryAgent',          'bi-chat-dots-fill',  'Menjawab pertanyaan pembeli di WA DM dengan RAG search',       'green'],
                            ['BroadcastAgent',         'bi-megaphone-fill',  'Pengiriman broadcast announcement ke anggota grup',             'amber'],
                            ['MemberOnboardingAgent',  'bi-person-plus-fill','Onboarding otomatis anggota baru yang bergabung ke grup',       'blue'],
                            ['GroupAdminReplyAgent',   'bi-reply-all-fill',  'Balas otomatis untuk moderasi dan pengumuman di grup',          'purple'],
                            ['MessageParserAgent',     'bi-braces-asterisk', 'Parsing struktur pesan kompleks berlampiran (caption + media)', 'rose'],
                        ];
                    @endphp
                    @foreach($agents as $ag)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-3 rounded-3" style="background:#f9fafb;border:1px solid #e5e7eb;">
                            <div class="feat-icon {{ $ag[3] }} flex-shrink-0" style="width:36px;height:36px;border-radius:10px;font-size:1rem;margin:0;">
                                <i class="bi {{ $ag[1] }}"></i>
                            </div>
                            <div>
                                <div style="font-size:.8rem;font-weight:800;color:#111827;font-family:monospace;">{{ $ag[0] }}</div>
                                <div style="font-size:.76rem;color:#6b7280;line-height:1.5;">{{ $ag[2] }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Comparison ──────────────────────────────────────── --}}
<section class="py-5" style="background:linear-gradient(135deg,#f9fafb,#f0fdf4);" id="perbandingan">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">Perbandingan</div>
            <div class="section-divider"></div>
            <h2 class="section-title">MarketplaceJamaah vs <span>Cara Konvensional</span></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="table-responsive comp-table">
                    <table class="table table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Aspek</th>
                                <th class="text-center">Grup WA Biasa</th>
                                <th class="text-center">MarketplaceJamaah AI ✨</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Iklan mudah tenggelam</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Iklan cepat hilang di chat</td>
                                <td class="text-center"><span class="check-yes">✓</span> Tersimpan permanen di marketplace</td>
                            </tr>
                            <tr>
                                <td><strong>Pencarian produk</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Cari manual di chat history</td>
                                <td class="text-center"><span class="check-yes">✓</span> Semantic search via WA bot atau website</td>
                            </tr>
                            <tr>
                                <td><strong>Format iklan</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Tidak konsisten, beda-beda</td>
                                <td class="text-center"><span class="check-yes">✓</span> AI standarisasi otomatis</td>
                            </tr>
                            <tr>
                                <td><strong>Moderasi konten</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Manual oleh admin</td>
                                <td class="text-center"><span class="check-yes">✓</span> AI 24/7 otomatis</td>
                            </tr>
                            <tr>
                                <td><strong>Halaman produk detail</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Tidak ada</td>
                                <td class="text-center"><span class="check-yes">✓</span> Setiap produk punya URL sendiri</td>
                            </tr>
                            <tr>
                                <td><strong>Profil penjual</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Tidak ada</td>
                                <td class="text-center"><span class="check-yes">✓</span> Halaman publik semua iklan penjual</td>
                            </tr>
                            <tr>
                                <td><strong>Kelola iklan penjual</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Tidak ada, harus upload manual</td>
                                <td class="text-center"><span class="check-yes">✓</span> Semua via chat bot WA, tanpa login</td>
                            </tr>
                            <tr>
                                <td><strong>Analitik & monitoring</strong></td>
                                <td class="text-center"><span class="check-no">✗</span> Tidak ada</td>
                                <td class="text-center"><span class="check-yes">✓</span> Dashboard admin + AI health monitor</td>
                            </tr>
                            <tr>
                                <td><strong>Butuh app baru</strong></td>
                                <td class="text-center"><span class="check-yes text-muted">–</span> Pakai WA existing</td>
                                <td class="text-center"><span class="check-yes">✓</span> Tetap pakai WA yang sudah ada</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── FAQ ──────────────────────────────────────────────── --}}
<section class="py-5" id="faq">
    <div class="container">
        <div class="text-center mb-5">
            <div class="section-eyebrow">FAQ</div>
            <div class="section-divider"></div>
            <h2 class="section-title">Pertanyaan yang <span>Sering Ditanyakan</span></h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-md-8">

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq1" aria-expanded="true">
                        Apakah penjual perlu membuat akun atau mendaftar?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq1" class="collapse show">
                        <div class="faq-body">
                            Tidak perlu daftar apapun. Cukup posting iklan di grup WhatsApp yang sudah terhubung ke platform — AI akan otomatis mendeteksi dan memproses iklan tersebut. Untuk mengelola iklan yang sudah tayang, penjual cukup chat langsung ke bot WhatsApp — ketik <strong>iklan saya</strong> untuk lihat daftar, atau <strong>edit #ID</strong> untuk ubah detail. Tidak ada login, tidak ada password.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq2" aria-expanded="false">
                        Berapa lama iklan muncul setelah diposting di grup?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq2" class="collapse">
                        <div class="faq-body">
                            Proses deteksi dan ekstraksi berjalan otomatis dalam <strong>1-3 menit</strong> setelah pesan diterima, tergantung antrian. Iklan yang lolos moderasi AI langsung tayang di marketplace tanpa perlu persetujuan manual admin.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq3" aria-expanded="false">
                        Bagaimana cara pembeli menghubungi penjual?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq3" class="collapse">
                        <div class="faq-body">
                            Di setiap halaman detail produk terdapat tombol "Hubungi Penjual via WhatsApp" yang langsung membuka chat WA dengan pesan otomatis menyebut nama produk. Transaksi berlangsung langsung antara pembeli dan penjual — platform tidak ikut campur.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq4" aria-expanded="false">
                        Format iklan apa yang wajib digunakan di grup?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq4" class="collapse">
                        <div class="faq-body">
                            Tidak ada format yang diwajibkan. AI bisa mengekstrak informasi dari teks bebas sekalipun. Namun, semakin lengkap informasi yang ditulis (nama produk, harga, deskripsi, lokasi, foto), semakin baik tampilan iklan di marketplace. Iklan tanpa gambar atau harga jelas mungkin tampil lebih sederhana.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq5" aria-expanded="false">
                        Apakah chatbot WA bisa menjawab pertanyaan di luar pencarian produk?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq5" class="collapse">
                        <div class="faq-body">
                            Ya! Bot dirancang untuk interaktif dan conversational. Selain pencarian produk dan penjual, bot bisa menjawab pertanyaan umum tentang marketplace, menjelaskan cara kerja platform, menampilkan daftar kategori tersedia, dan lain-lain. Ketika pesan tidak jelas (misal terlalu pendek atau ambigu), bot akan <strong>bertanya balik secara spesifik</strong> — pertanyaannya dihasilkan AI sesuai konteks, bukan jawaban generik.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq8" aria-expanded="false">
                        Bagaimana cara update lokasi bisnis di profil saya?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq8" class="collapse">
                        <div class="faq-body">
                            Cukup buka WhatsApp dan kirim <strong>pin lokasi</strong> ke nomor bot marketplace. Bot langsung bertanya: update lokasi bisnis (pilih 1️⃣) atau cari produk di sekitar lokasi itu (pilih 2️⃣). Jika pilih update, koordinat GPS dan alamat tersimpan otomatis ke profil — termasuk reverse-geocode alamat menggunakan Gemini AI.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq6" aria-expanded="false">
                        Bagaimana cara menggunakan chatbot WhatsApp untuk mencari produk?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq6" class="collapse">
                        <div class="faq-body">
                            Cukup kirim pesan ke nomor WhatsApp marketplace dengan bahasa natural, contoh:
                            <ul class="mt-2 mb-0" style="line-height:2;">
                                <li><code>"ada gamis ukuran L warna hitam?"</code></li>
                                <li><code>"cari penjual kue basah di Bekasi"</code></li>
                                <li><code>"tampilkan produk elektronik murah"</code></li>
                                <li><code>"iklan apa yang saya punya?"</code></li>
                            </ul>
                            Bot menggunakan AI berbasis RAG untuk memahami maksud dan menemukan produk paling relevan secara semantik.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-btn" data-bs-toggle="collapse" data-bs-target="#faq7" aria-expanded="false">
                        Apakah ada biaya untuk menggunakan platform ini?
                        <i class="bi bi-chevron-down faq-icon"></i>
                    </button>
                    <div id="faq7" class="collapse">
                        <div class="faq-body">
                            Platform ini dibangun untuk komunitas jamaah — hubungi admin komunitas untuk informasi lebih lanjut mengenai akses dan kebijakan penggunaan.
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- ── CTA ──────────────────────────────────────────────── --}}
<section class="py-5">
    <div class="container">
        <div class="cta-strip">
            <div style="position:relative;z-index:2;">
                <div class="d-inline-flex align-items-center gap-2 mb-3"
                     style="background:rgba(110,231,183,.15);border:1.5px solid rgba(110,231,183,.4);border-radius:100px;padding:.3rem 1.1rem;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#6ee7b7;">
                    <i class="bi bi-rocket-takeoff-fill"></i>&nbsp;Mulai Sekarang
                </div>
                <h2>Siap Bikin Marketplace<br>dari Grup WA Komunitas Kamu?</h2>
                <p>Posting di grup WhatsApp seperti biasa — AI yang mengurus sisanya. Gratis, tanpa daftar, tanpa login.</p>
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="{{ url('/') }}" class="btn-cta-white">
                        <i class="bi bi-grid"></i> Lihat Semua Produk
                    </a>
                    <a href="{{ url('/release-notes') }}" class="btn-cta-outline">
                        <i class="bi bi-journal-code"></i> Release Notes
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ── Footer ──────────────────────────────────────────── --}}
<footer class="site-footer">
    <div class="container">
        <div class="footer-logo">Marketplace<span>Jamaah</span> <span style="color:rgba(255,255,255,.4);font-weight:400;font-size:.85rem;"> AI</span></div>
        <p>Platform marketplace komunitas WhatsApp berbasis AI &bull; <a href="{{ url('/') }}">Lihat Produk</a> &bull; <a href="{{ url('/marketing-tools') }}">Fitur &amp; Cara Kerja</a> &bull; <a href="{{ url('/release-notes') }}">Release Notes</a></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
