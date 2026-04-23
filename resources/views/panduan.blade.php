@php
    $__loc = \App\Support\SiteLocale::get();
    $__t = fn($id, $en) => $__loc === 'en' ? $en : $id;
    $__langUrl = function($target) {
        $qs = array_merge(request()->query(), ['lang' => $target]);
        return url()->current() . '?' . http_build_query($qs);
    };
@endphp
<!DOCTYPE html>
<html lang="{{ $__loc }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $__t('Panduan Penggunaan — MarketplaceJamaah AI','User Guide — MarketplaceJamaah AI') }}</title>
    <meta name="description" content="{{ $__t('Panduan lengkap cara menggunakan MarketplaceJamaah: jual beli lewat WhatsApp Grup, interaksi dengan Bot AI via WA pribadi, dan fitur-fitur unggulan.','Complete guide to using MarketplaceJamaah: trading in WhatsApp groups, interacting with the AI bot via personal WA, and key features.') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --em:       #059669;
            --em-dark:  #047857;
            --em-light: #a7f3d0;
            --em-xlight:#ecfdf5;
            --em-mid:   #34d399;
            --amber:    #f59e0b;
            --amber-light:#fef3c7;
            --blue:     #3b82f6;
            --blue-light:#eff6ff;
            --purple:   #8b5cf6;
            --purple-light:#f5f3ff;
            --rose:     #f43f5e;
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
            box-shadow:0 4px 12px rgba(5,150,105,.45); flex-shrink:0;
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
            text-decoration:none; display:inline-flex; align-items:center; gap:.38rem; transition:all .18s;
        }
        .btn-nav-outline:hover { background:var(--em); color:#fff; border-color:var(--em); box-shadow:0 4px 14px rgba(5,150,105,.4); }

        /* ── Hero ─────────────────── */
        .hero-panduan {
            background:linear-gradient(135deg,#022c22 0%,#064e3b 50%,#065f46 100%);
            padding:4rem 0 3rem; text-align:center; position:relative; overflow:hidden;
        }
        .hero-panduan::before {
            content:''; position:absolute; inset:0;
            background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(52,211,153,.18) 0%,transparent 70%);
        }
        .hero-panduan .badge-pill {
            background:rgba(52,211,153,.18); border:1px solid rgba(52,211,153,.3);
            color:#6ee7b7; font-size:.72rem; font-weight:700; letter-spacing:.08em;
            padding:.35rem .9rem; border-radius:99px; display:inline-block; margin-bottom:1rem; text-transform:uppercase;
        }
        .hero-panduan h1 { color:#fff; font-size:clamp(1.8rem,4vw,2.8rem); font-weight:900; line-height:1.15; margin-bottom:.8rem; }
        .hero-panduan h1 span { color:#34d399; }
        .hero-panduan p { color:#a7f3d0; font-size:1rem; max-width:560px; margin:0 auto; }

        /* ── TOC Pills ─────────────── */
        .toc-pills { display:flex; flex-wrap:wrap; gap:.5rem; justify-content:center; padding:1.5rem 0 0; }
        .toc-pill {
            background:rgba(255,255,255,.1); color:#6ee7b7; border:1px solid rgba(110,231,183,.3);
            border-radius:99px; font-size:.78rem; font-weight:600; padding:.38rem .9rem;
            text-decoration:none; transition:all .18s;
        }
        .toc-pill:hover { background:rgba(52,211,153,.25); color:#fff; }

        /* ── Section cards ──────────── */
        .section-label {
            font-size:.68rem; font-weight:800; letter-spacing:.12em; text-transform:uppercase;
            color:var(--em); margin-bottom:.35rem;
        }
        .section-title { font-size:clamp(1.4rem,3vw,1.9rem); font-weight:900; color:#111827; margin-bottom:.5rem; }
        .section-lead { color:#4b5563; font-size:.95rem; max-width:600px; }

        /* ── Step cards ─────────────── */
        .step-card {
            background:#fff; border:1.5px solid #e5e7eb; border-radius:18px;
            padding:1.6rem 1.6rem 1.4rem; transition:all .2s; height:100%;
        }
        .step-card:hover { border-color:var(--em-light); box-shadow:0 8px 28px rgba(5,150,105,.1); transform:translateY(-2px); }
        .step-num {
            width:42px; height:42px; border-radius:12px; font-weight:900; font-size:1.1rem;
            display:flex; align-items:center; justify-content:center; margin-bottom:1rem; flex-shrink:0;
        }
        .step-title { font-size:1rem; font-weight:800; color:#111827; margin-bottom:.4rem; }
        .step-body { font-size:.88rem; color:#4b5563; line-height:1.6; }

        /* ── WAG steps horizontal ──── */
        .wag-step { display:flex; gap:1rem; align-items:flex-start; padding:1.2rem 1.4rem; background:#fff; border-radius:14px; border:1.5px solid #e5e7eb; margin-bottom:.75rem; transition:all .18s; }
        .wag-step:hover { border-color:var(--em-light); box-shadow:0 4px 16px rgba(5,150,105,.08); }
        .wag-step .step-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
        .wag-step .step-content h5 { font-size:.95rem; font-weight:800; color:#111827; margin-bottom:.25rem; }
        .wag-step .step-content p { font-size:.85rem; color:#6b7280; margin:0; line-height:1.55; }

        /* ── WA bubble mockup ─────── */
        .wa-bubble-wrap { background:#e5ddd5; border-radius:16px; padding:1.2rem; }
        .wa-bubble {
            display:inline-block; max-width:88%; padding:.6rem .9rem .5rem;
            border-radius:14px; font-size:.82rem; line-height:1.6; margin-bottom:.6rem;
            position:relative; word-break:break-word;
        }
        .wa-bubble.bot { background:#fff; border-bottom-left-radius:4px; align-self:flex-start; }
        .wa-bubble.user { background:#dcf8c6; border-bottom-right-radius:4px; align-self:flex-end; margin-left:auto; }
        .wa-bubble .time { font-size:.65rem; color:#999; text-align:right; margin-top:.15rem; }
        .wa-chat { display:flex; flex-direction:column; gap:.4rem; }

        /* ── Command table ────────── */
        .cmd-card {
            background:#fff; border:1.5px solid #e5e7eb; border-radius:14px;
            padding:1rem 1.2rem; display:flex; gap:.9rem; align-items:flex-start;
            transition:all .18s; margin-bottom:.6rem;
        }
        .cmd-card:hover { border-color:var(--em-light); box-shadow:0 4px 14px rgba(5,150,105,.08); }
        .cmd-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; flex-shrink:0; }
        .cmd-title { font-size:.88rem; font-weight:800; color:#111827; margin-bottom:.15rem; }
        .cmd-desc { font-size:.8rem; color:#6b7280; margin:0; line-height:1.5; }
        .cmd-example { font-size:.75rem; color:var(--em-dark); background:var(--em-xlight); border-radius:6px; padding:.15rem .5rem; display:inline-block; margin-top:.25rem; font-family:'Courier New',monospace; }

        /* ── Info alert box ────────── */
        .info-box {
            border-radius:14px; padding:1.1rem 1.3rem; display:flex; gap:.8rem; align-items:flex-start;
            font-size:.88rem; line-height:1.6;
        }
        .info-box.green { background:var(--em-xlight); border:1.5px solid var(--em-light); color:#065f46; }
        .info-box.amber { background:var(--amber-light); border:1.5px solid #fcd34d; color:#92400e; }
        .info-box.blue  { background:var(--blue-light);  border:1.5px solid #bfdbfe; color:#1e3a8a; }
        .info-box i { font-size:1.1rem; margin-top:.05rem; flex-shrink:0; }

        /* ── FAQ ─────────────────── */
        .faq-item { background:#fff; border:1.5px solid #e5e7eb; border-radius:14px; margin-bottom:.6rem; overflow:hidden; }
        .faq-q {
            padding:1rem 1.2rem; font-weight:700; font-size:.9rem; color:#111827;
            display:flex; justify-content:space-between; align-items:center; cursor:pointer;
            transition:background .15s;
        }
        .faq-q:hover { background:#f9fafb; }
        .faq-a { padding:0 1.2rem 1rem; font-size:.85rem; color:#4b5563; line-height:1.65; display:none; }
        .faq-item.open .faq-a { display:block; }
        .faq-item.open .faq-q { color:var(--em); }
        .faq-item.open .faq-icon { transform:rotate(45deg); }
        .faq-icon { transition:transform .2s; flex-shrink:0; }

        /* ── Footer ─────────────── */
        .pub-footer { background:#022c22; color:#94a3b8; padding:2rem 0; margin-top:5rem; font-size:.82rem; }
        .pub-footer a { color:#6ee7b7; text-decoration:none; }
        .pub-footer a:hover { color:#fff; }

        /* ── Utilities ─────────── */
        .bg-em    { background:var(--em); }
        .bg-em-xs { background:var(--em-xlight); }
        .bg-amber { background:var(--amber-light); }
        .bg-blue  { background:var(--blue-light); }
        .bg-purple{ background:var(--purple-light); }
        .bg-rose  { background:var(--rose-light); }
        .text-em  { color:var(--em); }
        .text-amber { color:var(--amber); }
        .text-blue { color:var(--blue); }
        .text-purple { color:var(--purple); }
        .text-rose { color:var(--rose); }

        code { background:var(--em-xlight); color:var(--em-dark); border-radius:5px; padding:.1rem .4rem; font-size:.82em; }

        @media(max-width:576px) {
            .hero-panduan { padding:3rem 0 2rem; }
            .toc-pills { gap:.35rem; }
        }
    </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="site-nav">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <a href="{{ route('landing') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <div class="brand-logo"><i class="bi bi-shop"></i></div>
                <div>
                    <div class="brand-name">Marketplace<span>Jamaah</span></div>
                    <div class="brand-sub">{{ $__t('Jual Beli Komunitas','Community Marketplace') }}</div>
                </div>
            </a>
            <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                <div style="display:inline-flex; padding:3px; background:#f1f5f9; border-radius:999px; border:1px solid #e5e7eb;">
                    <a href="{{ $__langUrl('id') }}" style="padding:.25rem .65rem; border-radius:999px; font-size:.72rem; font-weight:800; color:{{ $__loc==='id'?'#fff':'#475569' }}; background:{{ $__loc==='id'?'#0b1220':'transparent' }}; text-decoration:none;">ID</a>
                    <a href="{{ $__langUrl('en') }}" style="padding:.25rem .65rem; border-radius:999px; font-size:.72rem; font-weight:800; color:{{ $__loc==='en'?'#fff':'#475569' }}; background:{{ $__loc==='en'?'#0b1220':'transparent' }}; text-decoration:none;">EN</a>
                </div>
                <a href="{{ route('landing') }}" class="btn-nav-outline d-none d-sm-flex">
                    <i class="bi bi-grid"></i> {{ $__t('Produk','Products') }}
                </a>
                <a href="{{ url('/fitur') }}" class="btn-nav-outline d-none d-md-flex">
                    <i class="bi bi-stars"></i> {{ $__t('Fitur','Features') }}
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ── Hero ── -->
<section class="hero-panduan">
    <div class="container position-relative">
        <div class="badge-pill"><i class="bi bi-book me-1"></i>{{ $__t('Panduan Lengkap','Full Guide') }}</div>
        <h1>{{ $__t('Cara Menggunakan','How to Use') }}<br><span>MarketplaceJamaah</span></h1>
        <p>{{ $__t('Jual beli produk halal cukup lewat WhatsApp — tidak perlu aplikasi lain, tidak perlu daftar akun.','Buy and sell halal products through WhatsApp — no other apps, no account registration.') }}</p>
        @if($__loc==='en')
            <div style="margin:1rem auto 0; max-width:620px; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.18); border-radius:12px; padding:.7rem 1rem; color:#d1fae5; font-size:.85rem;">
                <i class="bi bi-info-circle me-1"></i> The bot itself speaks Indonesian. Commands like <code style="background:rgba(0,0,0,.3); color:#6ee7b7; padding:.1rem .4rem; border-radius:4px;">iklan saya</code> and <code style="background:rgba(0,0,0,.3); color:#6ee7b7; padding:.1rem .4rem; border-radius:4px;">edit #ID</code> must be typed as-is.
            </div>
        @endif
        <div class="toc-pills">
            <a href="#pasang-iklan-wag" class="toc-pill"><i class="bi bi-people me-1"></i>{{ $__t('Pasang Iklan via Grup','Post via Group') }}</a>
            <a href="#wapri-bot" class="toc-pill"><i class="bi bi-robot me-1"></i>{{ $__t('WA Pribadi & Bot AI','Personal WA & AI Bot') }}</a>
            <a href="#perintah-bot" class="toc-pill"><i class="bi bi-chat-dots me-1"></i>{{ $__t('Perintah Bot','Bot Commands') }}</a>
            <a href="#beli-produk" class="toc-pill"><i class="bi bi-bag-check me-1"></i>{{ $__t('Cara Beli','How to Buy') }}</a>
            <a href="#faq" class="toc-pill"><i class="bi bi-question-circle me-1"></i>FAQ</a>
        </div>
    </div>
</section>

<main>
<div class="container py-5">

    <!-- ══════════════════════════════════════════════════════════
         SECTION 1 — OVERVIEW
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-10">
            <div class="info-box green mb-4">
                <i class="bi bi-lightbulb-fill text-em"></i>
                <div>
                    <strong>{{ $__t('Konsep Dasar:','Core Concept:') }}</strong> {{ $__t('MarketplaceJamaah bekerja seperti grup jual-beli WhatsApp biasa — tapi setiap pesan yang kamu kirim di grup otomatis tampil sebagai iklan profesional di website ini. Bot AI kami menangani semua prosesnya secara otomatis.','MarketplaceJamaah works like a regular WhatsApp buy-sell group — but every message you post automatically appears as a professional listing on this site. Our AI bot handles the whole process.') }}
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-num bg-em-xs text-em">🏪</div>
                        <div class="step-title">{{ $__t('Marketplace Website','Marketplace Website') }}</div>
                        <div class="step-body">{{ $__t('Tampilkan & temukan produk dari komunitas di website publik yang bisa diakses siapa saja.','Show & discover community products on a public site accessible to anyone.') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-num bg-amber text-amber">👥</div>
                        <div class="step-title">{{ $__t('WhatsApp Grup (WAG)','WhatsApp Group (WAG)') }}</div>
                        <div class="step-body">{{ $__t('Kirim foto + deskripsi + harga di grup WA → iklan otomatis tayang di website dalam detik.','Send photo + description + price in the WA group → listing goes live on the site in seconds.') }}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-num bg-blue text-blue">🤖</div>
                        <div class="step-title">{{ $__t('Bot AI via WA Pribadi','AI Bot via Personal WA') }}</div>
                        <div class="step-body">{{ $__t('Chat langsung dengan Bot AI lewat WA pribadi untuk kelola iklan, cari produk, dan banyak lagi.','Chat directly with the AI bot via personal WA to manage listings, search products, and more.') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 2 — PASANG IKLAN VIA WAG
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5" id="pasang-iklan-wag">
        <div class="col-lg-10">
            <div class="section-label">{{ $__t('WhatsApp Grup','WhatsApp Group') }}</div>
            <div class="section-title">{{ $__t('Cara Pasang Iklan di Grup WA','How to Post a Listing in the WA Group') }}</div>
            <p class="section-lead mb-4">{{ $__t('Semudah chat biasa — tidak perlu login, tidak perlu isi form, tidak perlu aplikasi lain.','As easy as chatting — no login, no forms, no other apps.') }}</p>

            <div class="row g-4">
                <div class="col-md-6">
                    <!-- Steps -->
                    <div class="wag-step">
                        <div class="step-icon bg-em-xs" style="color:var(--em);">1️⃣</div>
                        <div class="step-content">
                            <h5>{{ $__t('Bergabung di Grup WhatsApp','Join the WhatsApp Group') }}</h5>
                            <p>{{ $__t('Masuk ke grup WhatsApp Marketplace Jamaah. Bot akan menyapa kamu lewat WA pribadi dan membantu proses pendaftaran.','Join the Marketplace Jamaah WhatsApp group. The bot will greet you in a personal WA chat and help you register.') }}</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon bg-amber">📸</div>
                        <div class="step-content">
                            <h5>{{ $__t('Kirim Foto atau Video Produk','Send a Product Photo or Video') }}</h5>
                            <p>{!! $__t('Di grup, kirim foto/video produkmu. Sertakan <strong>nama produk, harga, kondisi, dan lokasi</strong> di caption.','In the group, send your product photo/video. Include <strong>product name, price, condition, and location</strong> in the caption.') !!}</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon bg-blue" style="color:var(--blue);">🤖</div>
                        <div class="step-content">
                            <h5>{{ $__t('AI Proses Otomatis','AI Processes Automatically') }}</h5>
                            <p>{{ $__t('Bot AI membaca pesanmu, membersihkan teks, menentukan kategori, dan membuat deskripsi iklan yang menarik.','The AI bot reads your message, cleans up the text, picks a category, and writes an attractive listing description.') }}</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon bg-em-xs" style="color:var(--em);">🌐</div>
                        <div class="step-content">
                            <h5>{{ $__t('Iklan Tayang di Website','Listing Appears on the Website') }}</h5>
                            <p>{!! $__t('Dalam hitungan detik, iklanmu muncul di <strong>marketplacejamaah-ai.jodyaryono.id</strong> lengkap dengan foto, harga, dan link produk.','In seconds, your listing appears on <strong>marketplacejamaah-ai.jodyaryono.id</strong> with photo, price, and product link.') !!}</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon" style="background:var(--purple-light);color:var(--purple);">🔔</div>
                        <div class="step-content">
                            <h5>{{ $__t('Dapat Konfirmasi via WA Pribadi','Receive Confirmation via Personal WA') }}</h5>
                            <p>{{ $__t('Bot mengirimkan konfirmasi ke WA pribadimu lengkap dengan link iklan, ID iklan, dan ringkasan produk.','The bot sends you a confirmation in your personal WA with the listing link, ID, and product summary.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Contoh pesan WAG -->
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">{{ $__t('CONTOH PESAN DI GRUP WA','EXAMPLE MESSAGE IN WA GROUP') }}</div>
                    <div class="wa-bubble-wrap">
                        <div class="wa-chat">
                            <div class="wa-bubble user">
                                <strong>📸 [foto kue sus]</strong><br>
                                Kue Sus Homemade Semarang<br>
                                Harga: Rp 25.000 / kotak isi 12<br>
                                Kondisi: Fresh baked daily<br>
                                Lokasi: Tembalang, Semarang
                                <div class="time">08.34 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                ✅ <strong>Iklan Diterima!</strong><br><br>
                                🍮 <em>Kue Sus Homemade Semarang</em><br>
                                💰 Rp 25.000 / kotak<br>
                                📍 Tembalang, Semarang<br><br>
                                🌐 Iklanmu sudah tayang!<br>
                                👉 marketplacejamaah-ai.jodyaryono.id/p/142
                                <div class="time">08.34 ✓✓</div>
                            </div>
                        </div>
                    </div>

                    <div class="info-box amber mt-3">
                        <i class="bi bi-exclamation-triangle-fill text-amber"></i>
                        <div>
                            <strong>{{ $__t('Tips agar iklan tampil lebih baik:','Tips for better-looking listings:') }}</strong><br>
                            {{ $__t('Sertakan harga yang jelas, kondisi produk (baru/bekas), dan lokasi kota. Foto yang terang dan produk terlihat jelas = lebih banyak calon pembeli.','Include a clear price, condition (new/used), and city. Bright, clear photos = more interested buyers.') }}
                        </div>
                    </div>

                    <div class="info-box green mt-2">
                        <i class="bi bi-lightning-charge-fill text-em"></i>
                        <div>
                            <strong>{{ $__t('Iklan lama otomatis diganti:','Old listings auto-replaced:') }}</strong> {{ $__t('Jika kamu kirim produk yang sama lagi, sistem otomatis menghapus iklan lama dan menampilkan yang terbaru.','If you post the same product again, the system automatically replaces the old listing with the new one.') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Jenis konten yang didukung -->
            <div class="mt-4">
                <div class="fw-700 mb-2" style="font-size:.88rem;color:#374151;">{{ $__t('Jenis konten yang didukung di grup:','Content types supported in the group:') }}</div>
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">📸</div>
                            <strong>{{ $__t('Foto','Photo') }}</strong><br>
                            <span style="color:#6b7280;">JPG / PNG</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">🎥</div>
                            <strong>{{ $__t('Video','Video') }}</strong><br>
                            <span style="color:#6b7280;">MP4 / MOV</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">📝</div>
                            <strong>{{ $__t('Teks saja','Text only') }}</strong><br>
                            <span style="color:#6b7280;">{{ $__t('Iklan baris','Classified ad') }}</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">📍</div>
                            <strong>{{ $__t('Lokasi','Location') }}</strong><br>
                            <span style="color:#6b7280;">{{ $__t('Via WA Pribadi','Via Personal WA') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 3 — WA PRIBADI & BOT AI
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5" id="wapri-bot">
        <div class="col-lg-10">
            <div class="section-label">{{ $__t('WA Pribadi (DM)','Personal WA (DM)') }}</div>
            <div class="section-title">{{ $__t('Interaksi dengan Bot AI Lewat WA Pribadi','Chat with the AI Bot via Personal WA') }}</div>
            <p class="section-lead mb-4">{{ $__t('Bot AI siap membantu 24 jam — tidak perlu hafal perintah khusus, cukup chat natural dalam Bahasa Indonesia.','The AI bot is ready 24/7 — no special commands needed, just chat naturally in Indonesian.') }}</p>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="info-box blue mb-3">
                        <i class="bi bi-info-circle-fill text-blue"></i>
                        <div>
                            {{ $__t('Setelah bergabung di grup, Bot akan kirim pesan perkenalan ke WA pribadimu. Balas pesan itu — itulah cara kamu mulai berinteraksi dengan Bot AI.','After joining the group, the bot sends an intro message to your personal WA. Reply to it — that\'s how you start interacting with the AI bot.') }}
                        </div>
                    </div>

                    <div class="info-box green">
                        <i class="bi bi-shield-check-fill text-em"></i>
                        <div>
                            <strong>{{ $__t('Privasi terjaga:','Privacy protected:') }}</strong> {{ $__t('Semua percakapan di WA Pribadi hanya antara kamu dan Bot. Anggota grup lain tidak bisa melihat riwayat chat ini.','All conversations in Personal WA are only between you and the bot. Other group members cannot see this chat history.') }}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">{{ $__t('CONTOH PERCAKAPAN','EXAMPLE CONVERSATION') }}</div>
                    <div class="wa-bubble-wrap">
                        <div class="wa-chat">
                            <div class="wa-bubble bot">
                                Assalamu'alaikum <strong>Budi</strong>! 🙏<br><br>
                                Selamat datang di <strong>Marketplace Jamaah!</strong><br>
                                Nama Kakak siapa? 😊
                                <div class="time">10.00 ✓✓</div>
                            </div>
                            <div class="wa-bubble user">
                                Wa'alaikumsalam, saya Budi dari Bandung
                                <div class="time">10.01 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                Senang kenal, <strong>Kak Budi</strong>! 😊<br>
                                Apakah Kakak mau beli, jual, atau keduanya?<br><br>
                                1️⃣ Saya ingin <strong>jual</strong> produk<br>
                                2️⃣ Saya ingin <strong>beli</strong> produk<br>
                                3️⃣ <strong>Keduanya</strong>
                                <div class="time">10.01 ✓✓</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 4 — PERINTAH BOT (LENGKAP)
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5" id="perintah-bot">
        <div class="col-lg-10">
            <div class="section-label">{{ $__t('Panduan Perintah','Command Guide') }}</div>
            <div class="section-title">{{ $__t('Apa Saja yang Bisa Kamu Minta ke Bot?','What Can You Ask the Bot?') }}</div>
            <p class="section-lead mb-4">{{ $__t('Tulis pesan natural dalam Bahasa Indonesia — Bot AI memahami maksudmu tanpa perlu perintah kaku.','Write natural messages in Indonesian — the AI bot understands intent without rigid commands.') }}</p>

            <!-- Kategori 1: Iklan -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--em-xlight);color:var(--em);padding:.2rem .6rem;border-radius:8px;">🛍️ {{ $__t('Kelola Iklan','Manage Listings') }}</span>
            </h5>
            <div class="mb-4">
                <div class="cmd-card">
                    <div class="cmd-icon bg-em-xs text-em">📸</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Buat Iklan Baru via Bot','Create a New Listing via Bot') }}</div>
                        <div class="cmd-desc">{{ $__t('Kirim foto produk ke WA Pribadi Bot — AI otomatis buat draft iklan profesional, kamu tinggal review & setuju.','Send a product photo to the bot on Personal WA — AI drafts a professional listing; you just review & approve.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "buat iklan" {{ $__t('atau langsung kirim foto','or just send a photo') }}</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-amber text-amber">✏️</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Edit Iklan','Edit Listing') }}</div>
                        <div class="cmd-desc">{{ $__t('Ubah judul, harga, deskripsi, atau lokasi iklanmu. Sebutkan ID iklan dan perubahan yang diinginkan.','Change title, price, description, or location. Mention the listing ID and your desired change.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "edit iklan #142" {{ $__t('atau','or') }} "ubah harga iklan 142 jadi 30rb"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-blue text-blue">📋</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Lihat Iklanku','View My Listings') }}</div>
                        <div class="cmd-desc">{{ $__t('Tampilkan daftar semua iklan aktif milikmu lengkap dengan ID, judul, dan status.','Show the list of all your active listings with ID, title, and status.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "iklan saya" {{ $__t('atau','or') }} "lihat iklanku"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--rose-light);color:var(--rose);">✅</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Tandai Terjual','Mark as Sold') }}</div>
                        <div class="cmd-desc">{{ $__t('Nonaktifkan iklan setelah produk berhasil terjual. Iklan hilang dari website secara otomatis.','Deactivate a listing after it\'s sold. The listing disappears from the site automatically.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "terjual #142" {{ $__t('atau','or') }} "laku iklan 142"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--purple-light);color:var(--purple);">🔄</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Aktifkan Kembali Iklan','Reactivate Listing') }}</div>
                        <div class="cmd-desc">{{ $__t('Aktifkan lagi iklan yang sebelumnya telah ditandai terjual atau dinonaktifkan.','Re-activate a listing previously marked as sold or deactivated.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "aktifkan #142" {{ $__t('atau','or') }} "aktifkan kembali iklan 142"</div>
                    </div>
                </div>
            </div>

            <!-- Kategori 2: Cari Produk -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--blue-light);color:var(--blue);padding:.2rem .6rem;border-radius:8px;">🔍 {{ $__t('Cari Produk & Penjual','Find Products & Sellers') }}</span>
            </h5>
            <div class="mb-4">
                <div class="cmd-card">
                    <div class="cmd-icon bg-blue text-blue">🛒</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Cari Produk','Search Products') }}</div>
                        <div class="cmd-desc">{{ $__t('Cari produk yang tersedia di marketplace. Bot akan tampilkan produk relevan beserta harga dan link.','Search products available on the marketplace. The bot shows relevant items with price and link.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "ada jual kerudung?" {{ $__t('atau','or') }} "cari madu asli"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-em-xs text-em">👤</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Cari Penjual','Find Sellers') }}</div>
                        <div class="cmd-desc">{{ $__t('Temukan penjual berdasarkan kategori atau nama produk yang mereka jual.','Find sellers by category or by the products they sell.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "cari penjual makanan" {{ $__t('atau','or') }} "siapa yang jual batik?"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-amber text-amber">📂</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Lihat Kategori','View Categories') }}</div>
                        <div class="cmd-desc">{{ $__t('Tampilkan semua kategori produk yang tersedia di marketplace.','Show all product categories available on the marketplace.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "kategori apa saja?" {{ $__t('atau','or') }} "daftar kategori"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--purple-light);color:var(--purple);">📍</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Cari Produk Terdekat','Find Nearby Products') }}</div>
                        <div class="cmd-desc">{{ $__t('Bagikan lokasi WA kamu → Bot cari produk di sekitar lokasimu secara otomatis.','Share your WA location → bot finds nearby products automatically.') }}</div>
                        <div class="cmd-example">{!! $__t('Kirim <strong>Lokasi</strong> WhatsApp → pilih opsi <em>"Cari produk di sekitar lokasi ini"</em>','Send a WhatsApp <strong>Location</strong> → choose <em>"Search products near this location"</em>') !!}</div>
                    </div>
                </div>
            </div>

            <!-- Kategori 3: Profil & Akun -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--purple-light);color:var(--purple);padding:.2rem .6rem;border-radius:8px;">👤 {{ $__t('Profil & Akun','Profile & Account') }}</span>
            </h5>
            <div class="mb-4">
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--purple-light);color:var(--purple);">🗂️</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Update Lokasi Bisnis','Update Business Location') }}</div>
                        <div class="cmd-desc">{{ $__t('Bagikan lokasi WA → pilih opsi update lokasi bisnis agar pembeli tahu kamu berjualan dari mana.','Share a WA location → pick "update business location" so buyers know where you operate from.') }}</div>
                        <div class="cmd-example">{!! $__t('Kirim <strong>Lokasi</strong> WhatsApp → pilih opsi <em>"Update lokasi bisnis saya"</em>','Send a WhatsApp <strong>Location</strong> → choose <em>"Update my business location"</em>') !!}</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-blue text-blue">🪪</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Scan KTP (Verifikasi Identitas)','Scan ID Card (Identity Verification)') }}</div>
                        <div class="cmd-desc">{{ $__t('Kirim foto KTP → Bot AI membaca dan mengisi data profilmu secara otomatis (nama, alamat, dll).','Send an ID card photo → the AI bot reads it and auto-fills your profile (name, address, etc).') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "scan KTP" → {{ $__t('lalu kirim foto KTP','then send the ID photo') }}</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-em-xs text-em">📞</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Hubungi Admin','Contact Admin') }}</div>
                        <div class="cmd-desc">{{ $__t('Bot meneruskan pesanmu ke admin untuk masalah yang perlu penanganan manusia.','The bot forwards your message to the admin for issues that need a human.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "hubungi admin" {{ $__t('atau','or') }} "minta bantuan admin"</div>
                    </div>
                </div>
            </div>

            <!-- Kategori 4: Lainnya -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--amber-light);color:#92400e;padding:.2rem .6rem;border-radius:8px;">ℹ️ {{ $__t('Lainnya','Other') }}</span>
            </h5>
            <div class="mb-2">
                <div class="cmd-card">
                    <div class="cmd-icon bg-amber text-amber">❓</div>
                    <div>
                        <div class="cmd-title">{{ $__t('Bantuan / Daftar Perintah','Help / Command List') }}</div>
                        <div class="cmd-desc">{{ $__t('Tampilkan daftar lengkap fitur dan cara penggunaan bot langsung di chat.','Show the full feature list and bot usage directly in chat.') }}</div>
                        <div class="cmd-example">{{ $__t('Ketik','Type') }}: "bantuan" {{ $__t('atau','or') }} "help"</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 5 — CARA BELI
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5" id="beli-produk">
        <div class="col-lg-10">
            <div class="section-label">{{ $__t('Untuk Pembeli','For Buyers') }}</div>
            <div class="section-title">{{ $__t('Cara Membeli Produk','How to Buy') }}</div>
            <p class="section-lead mb-4">{{ $__t('MarketplaceJamaah adalah platform listing — transaksi dilakukan langsung antara pembeli dan penjual.','MarketplaceJamaah is a listing platform — transactions happen directly between buyer and seller.') }}</p>

            <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num bg-em-xs text-em mx-auto" style="font-size:1.5rem;width:52px;height:52px;">🔍</div>
                        <div class="step-title mt-2">{{ $__t('Cari Produk','Search Products') }}</div>
                        <div class="step-body">{{ $__t('Browse website atau chat bot dengan kata kunci produk yang dicari.','Browse the site or chat the bot with keywords for the product you want.') }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num bg-amber mx-auto" style="font-size:1.5rem;width:52px;height:52px;">📄</div>
                        <div class="step-title mt-2">{{ $__t('Lihat Detail','View Detail') }}</div>
                        <div class="step-body">{{ $__t('Klik produk untuk melihat foto lengkap, deskripsi, harga, dan info penjual.','Click a product to see the full photo, description, price, and seller info.') }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num bg-blue text-blue mx-auto" style="font-size:1.5rem;width:52px;height:52px;">💬</div>
                        <div class="step-title mt-2">{{ $__t('Hubungi Penjual','Contact Seller') }}</div>
                        <div class="step-body">{{ $__t('Klik tombol "Chat Penjual" — otomatis terhubung ke WA penjual.','Click the "Chat Seller" button — opens WA with the seller directly.') }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num" style="background:var(--purple-light);color:var(--purple);font-size:1.5rem;width:52px;height:52px;margin:auto;">🤝</div>
                        <div class="step-title mt-2">{{ $__t('Sepakati & Bayar','Agree & Pay') }}</div>
                        <div class="step-body">{{ $__t('Negosiasi harga dan metode pengiriman langsung dengan penjual di WA.','Negotiate price and shipping directly with the seller on WA.') }}</div>
                    </div>
                </div>
            </div>

            <div class="info-box amber mt-4">
                <i class="bi bi-shield-exclamation text-amber"></i>
                <div>
                    <strong>{{ $__t('Penting:','Important:') }}</strong> {!! $__t('MarketplaceJamaah <strong>bukan</strong> perantara transaksi. Kami menyediakan platform listing saja. Selalu berhati-hati dalam bertransaksi — pastikan kamu kenal atau percaya kepada penjual, terutama untuk transaksi bernilai besar.','MarketplaceJamaah is <strong>not</strong> a transaction intermediary. We only provide a listing platform. Always be careful — make sure you know or trust the seller, especially for high-value transactions.') !!}
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 6 — CONTOH PERCAKAPAN LENGKAP
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-10">
            <div class="section-label">{{ $__t('Contoh Nyata','Real Example') }}</div>
            <div class="section-title">{{ $__t('Sesi Lengkap Buat Iklan via WA Pribadi','Full Session: Create a Listing via Personal WA') }}</div>
            <p class="section-lead mb-4">{{ $__t('Kamu bisa buat iklan yang lebih kaya lewat WA Pribadi menggunakan fitur Ad Builder AI.','Create richer listings via Personal WA using the AI Ad Builder.') }}</p>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">{{ $__t('SESI AD BUILDER — LANGKAH DEMI LANGKAH','AD BUILDER SESSION — STEP BY STEP') }}</div>
                    <div class="wa-bubble-wrap">
                        <div class="wa-chat">
                            <div class="wa-bubble user">
                                buat iklan
                                <div class="time">09.00 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                🛍️ <strong>Buat Iklan Baru</strong><br><br>
                                Mari buat iklan yang menarik bersama AI 🤖<br><br>
                                1️⃣ Kirim <strong>foto produk</strong> kamu<br>
                                2️⃣ AI buat draft iklan profesional<br>
                                3️⃣ Review → setujui → tayang di grup!<br><br>
                                📸 <strong>Silakan kirim foto produk sekarang!</strong>
                                <div class="time">09.00 ✓✓</div>
                            </div>
                            <div class="wa-bubble user">
                                <em>[foto hijab motif batik]</em><br>
                                Hijab segiempat batik, Rp 85rb, baru, Jogja
                                <div class="time">09.01 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                ⏳ <em>Sedang menganalisis foto dan membuat draft...</em>
                                <div class="time">09.01 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                ✨ <strong>Draft Iklanmu Siap!</strong><br><br>
                                <strong>Hijab Segiempat Batik Premium</strong><br>
                                Kategori: Fashion Muslim<br>
                                💰 Rp 85.000<br>
                                📍 Jogjakarta<br><br>
                                <em>Hijab segiempat motif batik khas Jawa yang elegan dan nyaman. Bahan adem, cocok untuk acara formal maupun kasual. Kondisi baru.</em><br><br>
                                Ketik <strong>ya</strong> untuk posting, atau kirim koreksi
                                <div class="time">09.02 ✓✓</div>
                            </div>
                            <div class="wa-bubble user">
                                ya
                                <div class="time">09.02 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                🎉 <strong>Iklan sudah tayang!</strong><br>
                                ID: #<strong>203</strong><br>
                                👉 marketplacejamaah-ai.jodyaryono.id/p/203
                                <div class="time">09.02 ✓✓</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">{{ $__t('CONTOH EDIT IKLAN','EDIT LISTING EXAMPLE') }}</div>
                    <div class="wa-bubble-wrap mb-3">
                        <div class="wa-chat">
                            <div class="wa-bubble user">
                                edit iklan #203 ubah harga jadi 75rb
                                <div class="time">14.30 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                ✅ Iklan <strong>#203</strong> berhasil diperbarui!<br>
                                💰 Harga: <s>Rp 85.000</s> → <strong>Rp 75.000</strong><br><br>
                                Iklan sudah tampil dengan harga baru.
                                <div class="time">14.30 ✓✓</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">{{ $__t('CONTOH TANDAI TERJUAL','MARK AS SOLD EXAMPLE') }}</div>
                    <div class="wa-bubble-wrap mb-3">
                        <div class="wa-chat">
                            <div class="wa-bubble user">
                                terjual #203
                                <div class="time">16.00 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                🎊 Alhamdulillah, iklan <strong>#203</strong> berhasil ditandai terjual!<br><br>
                                Iklan sudah dihapus dari website. Semoga berkah! 🙏
                                <div class="time">16.00 ✓✓</div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">{{ $__t('CONTOH CARI PRODUK','PRODUCT SEARCH EXAMPLE') }}</div>
                    <div class="wa-bubble-wrap">
                        <div class="wa-chat">
                            <div class="wa-bubble user">
                                ada jual kurma ajwa?
                                <div class="time">11.15 ✓✓</div>
                            </div>
                            <div class="wa-bubble bot">
                                🛍️ <strong>3 Produk Ditemukan</strong><br><br>
                                1. <strong>Kurma Ajwa Madinah 500gr</strong><br>
                                   💰 Rp 120.000 📍 Jakarta<br>
                                   👤 Kak Sarah<br>
                                   👉 .../p/87<br><br>
                                2. <strong>Kurma Ajwa Premium Import</strong><br>
                                   💰 Rp 150.000 📍 Bandung<br>
                                   👤 Kak Ridwan<br>
                                   👉 .../p/134
                                <div class="time">11.15 ✓✓</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 7 — FAQ
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5" id="faq">
        <div class="col-lg-10">
            <div class="section-label">FAQ</div>
            <div class="section-title">{{ $__t('Pertanyaan yang Sering Ditanyakan','Frequently Asked Questions') }}</div>
            <p class="section-lead mb-4">{{ $__t('Temukan jawaban untuk pertanyaan umum seputar MarketplaceJamaah.','Find answers to common questions about MarketplaceJamaah.') }}</p>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Apakah harus bayar untuk pasang iklan?','Do I have to pay to post a listing?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    <strong>{{ $__t('Tidak ada biaya sama sekali.','No cost at all.') }}</strong> {{ $__t('MarketplaceJamaah gratis untuk semua anggota komunitas jamaah. Pasang iklan sebanyak yang kamu mau tanpa biaya.','MarketplaceJamaah is free for all jamaah community members. Post as many listings as you want at no cost.') }}
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Bagaimana cara bergabung di grup WhatsApp?','How do I join the WhatsApp group?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    {{ $__t('Hubungi admin marketplace untuk mendapatkan link undangan grup. Setelah bergabung, Bot AI akan secara otomatis menyapa kamu lewat WA pribadi dan membimbing proses pendaftaran.','Contact the marketplace admin for a group invite link. Once you join, the AI bot automatically greets you in personal WA and guides you through registration.') }}
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Berapa lama iklan saya akan tayang?','How long does my listing stay live?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    {{ $__t('Iklan tayang selama produk masih tersedia. Iklan akan otomatis hilang jika kamu menandainya sebagai terjual, dihapus dari grup, atau jika kamu keluar dari grup. Tidak ada batas waktu tayangnya selama statusnya aktif.','Listings stay live while the product is available. They disappear automatically when marked sold, deleted from the group, or when you leave the group. No time limit while active.') }}
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Apakah bisa pasang iklan tanpa foto?','Can I post a listing without a photo?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    {!! $__t('Bisa! Kirim teks saja di grup → iklan akan tampil sebagai <em>iklan baris</em> di bagian bawah website. Tapi iklan dengan foto/video punya tampilan yang jauh lebih menarik dan lebih mudah ditemukan oleh pembeli.','Yes! Send text-only in the group → it shows as a <em>classified ad</em> at the bottom of the site. But listings with photo/video look much better and are easier to find.') !!}
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Bot tidak merespons pesan saya — apa yang harus dilakukan?','The bot doesn\'t respond to my message — what should I do?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    {!! $__t('Coba kirim pesan <code>bantuan</code> atau <code>halo</code> ke nomor Bot. Jika masih tidak merespons, hubungi admin. Pastikan kamu mengirim DM ke nomor Bot, bukan ke grup. Bot hanya aktif membalas pesan di WA Pribadi, bukan di dalam grup.','Try sending <code>bantuan</code> or <code>halo</code> to the bot number. If still no response, contact the admin. Make sure you DM the bot number, not the group. The bot replies in Personal WA only, not inside the group.') !!}
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Apakah MarketplaceJamaah memproses pembayaran?','Does MarketplaceJamaah process payments?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    {{ $__t('Tidak. MarketplaceJamaah adalah platform listing — kami hanya mempertemukan penjual dan pembeli. Semua transaksi dilakukan secara mandiri antara pembeli dan penjual lewat WhatsApp atau cara yang mereka sepakati.','No. MarketplaceJamaah is a listing platform — we only match sellers and buyers. All transactions happen independently between buyer and seller via WhatsApp or their agreed method.') }}
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Bisa pasang lebih dari satu iklan?','Can I post more than one listing?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    {{ $__t('Tentu! Kamu bisa pasang iklan sebanyak yang kamu inginkan. Setiap pesan foto/video/teks baru yang kamu kirim di grup akan diproses sebagai iklan terpisah. Untuk produk yang sama, sistem otomatis mengganti iklan lama dengan yang baru.','Of course! Post as many listings as you want. Every new photo/video/text in the group is processed as a separate listing. For the same product, the system automatically replaces the old listing with the new one.') }}
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    {{ $__t('Apakah semua produk boleh dijual?','Can any product be sold here?') }}
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    {!! $__t('MarketplaceJamaah khusus untuk produk <strong>halal</strong>. Dilarang menjual produk yang mengandung riba, produk haram (alkohol, rokok, dll), produk ilegal, atau produk yang menipu. Admin berhak menghapus iklan dan memblokir pengguna yang melanggar ketentuan.','MarketplaceJamaah is specifically for <strong>halal</strong> products. Selling interest-based products, haram items (alcohol, cigarettes, etc.), illegal goods, or deceptive products is prohibited. Admins may remove listings and block users who violate the rules.') !!}
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         CTA
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center p-4 p-md-5 rounded-4" style="background:linear-gradient(135deg,#022c22,#065f46);color:#fff;">
                <div style="font-size:2.5rem;margin-bottom:.5rem;">🕌</div>
                <h3 class="fw-900 mb-2" style="color:#fff;">{{ $__t('Siap Bergabung?','Ready to Join?') }}</h3>
                <p style="color:#a7f3d0;max-width:480px;margin:0 auto 1.5rem;font-size:.95rem;">
                    {{ $__t('Gabung komunitas jamaah yang jual beli dengan mudah, amanah, dan berkah lewat WhatsApp.','Join the jamaah community trading easily, honestly, and with blessings through WhatsApp.') }}
                </p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="{{ route('landing') }}" class="btn-nav-primary">
                        <i class="bi bi-shop"></i> {{ $__t('Lihat Produk','View Products') }}
                    </a>
                    <a href="{{ url('/fitur') }}" style="background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:10px;font-size:.82rem;font-weight:700;padding:.48rem 1.2rem;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;">
                        <i class="bi bi-stars"></i> {{ $__t('Lihat Semua Fitur','See All Features') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
</main>

<!-- ── Footer ── -->
<footer class="pub-footer">
    <div class="container">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div>© {{ date('Y') }} MarketplaceJamaah · {{ $__t('Jual beli komunitas halal Indonesia','Halal Indonesia community marketplace') }}</div>
            <div class="d-flex gap-3 flex-wrap">
                <a href="{{ route('landing') }}">{{ $__t('Beranda','Home') }}</a>
                <a href="{{ url('/fitur') }}">{{ $__t('Fitur','Features') }}</a>
                <a href="{{ route('panduan') }}">{{ $__t('Panduan','Guide') }}</a>
                <a href="{{ route('release-notes') }}">Release Notes</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleFaq(el) {
        const item = el.closest('.faq-item');
        const isOpen = item.classList.contains('open');
        document.querySelectorAll('.faq-item').forEach(i => i.classList.remove('open'));
        if (!isOpen) item.classList.add('open');
    }
</script>
</body>
</html>
