<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panduan Penggunaan — MarketplaceJamaah AI</title>
    <meta name="description" content="Panduan lengkap cara menggunakan MarketplaceJamaah: jual beli lewat WhatsApp Grup, interaksi dengan Bot AI via WA pribadi, dan fitur-fitur unggulan.">
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
                    <div class="brand-sub">Jual Beli Komunitas</div>
                </div>
            </a>
            <div class="d-flex gap-2 align-items-center flex-wrap justify-content-end">
                <a href="{{ route('landing') }}" class="btn-nav-outline d-none d-sm-flex">
                    <i class="bi bi-grid"></i> Produk
                </a>
                <a href="{{ route('marketing-tools') }}" class="btn-nav-outline d-none d-md-flex">
                    <i class="bi bi-stars"></i> Fitur
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ── Hero ── -->
<section class="hero-panduan">
    <div class="container position-relative">
        <div class="badge-pill"><i class="bi bi-book me-1"></i>Panduan Lengkap</div>
        <h1>Cara Menggunakan<br><span>MarketplaceJamaah</span></h1>
        <p>Jual beli produk halal cukup lewat WhatsApp — tidak perlu aplikasi lain, tidak perlu daftar akun.</p>
        <div class="toc-pills">
            <a href="#pasang-iklan-wag" class="toc-pill"><i class="bi bi-people me-1"></i>Pasang Iklan via Grup</a>
            <a href="#wapri-bot" class="toc-pill"><i class="bi bi-robot me-1"></i>WA Pribadi & Bot AI</a>
            <a href="#perintah-bot" class="toc-pill"><i class="bi bi-chat-dots me-1"></i>Perintah Bot</a>
            <a href="#beli-produk" class="toc-pill"><i class="bi bi-bag-check me-1"></i>Cara Beli</a>
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
                    <strong>Konsep Dasar:</strong> MarketplaceJamaah bekerja seperti grup jual-beli WhatsApp biasa — tapi setiap pesan yang kamu kirim di grup otomatis tampil sebagai iklan profesional di website ini. Bot AI kami menangani semua prosesnya secara otomatis.
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-num bg-em-xs text-em">🏪</div>
                        <div class="step-title">Marketplace Website</div>
                        <div class="step-body">Tampilkan & temukan produk dari komunitas di website publik yang bisa diakses siapa saja.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-num bg-amber text-amber">👥</div>
                        <div class="step-title">WhatsApp Grup (WAG)</div>
                        <div class="step-body">Kirim foto + deskripsi + harga di grup WA → iklan otomatis tayang di website dalam detik.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-num bg-blue text-blue">🤖</div>
                        <div class="step-title">Bot AI via WA Pribadi</div>
                        <div class="step-body">Chat langsung dengan Bot AI lewat WA pribadi untuk kelola iklan, cari produk, dan banyak lagi.</div>
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
            <div class="section-label">WhatsApp Grup</div>
            <div class="section-title">Cara Pasang Iklan di Grup WA</div>
            <p class="section-lead mb-4">Semudah chat biasa — tidak perlu login, tidak perlu isi form, tidak perlu aplikasi lain.</p>

            <div class="row g-4">
                <div class="col-md-6">
                    <!-- Steps -->
                    <div class="wag-step">
                        <div class="step-icon bg-em-xs" style="color:var(--em);">1️⃣</div>
                        <div class="step-content">
                            <h5>Bergabung di Grup WhatsApp</h5>
                            <p>Masuk ke grup WhatsApp Marketplace Jamaah. Bot akan menyapa kamu lewat WA pribadi dan membantu proses pendaftaran.</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon bg-amber">📸</div>
                        <div class="step-content">
                            <h5>Kirim Foto atau Video Produk</h5>
                            <p>Di grup, kirim foto/video produkmu. Sertakan <strong>nama produk, harga, kondisi, dan lokasi</strong> di caption.</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon bg-blue" style="color:var(--blue);">🤖</div>
                        <div class="step-content">
                            <h5>AI Proses Otomatis</h5>
                            <p>Bot AI membaca pesanmu, membersihkan teks, menentukan kategori, dan membuat deskripsi iklan yang menarik.</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon bg-em-xs" style="color:var(--em);">🌐</div>
                        <div class="step-content">
                            <h5>Iklan Tayang di Website</h5>
                            <p>Dalam hitungan detik, iklanmu muncul di <strong>marketplacejamaah-ai.jodyaryono.id</strong> lengkap dengan foto, harga, dan link produk.</p>
                        </div>
                    </div>
                    <div class="wag-step">
                        <div class="step-icon" style="background:var(--purple-light);color:var(--purple);">🔔</div>
                        <div class="step-content">
                            <h5>Dapat Konfirmasi via WA Pribadi</h5>
                            <p>Bot mengirimkan konfirmasi ke WA pribadimu lengkap dengan link iklan, ID iklan, dan ringkasan produk.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <!-- Contoh pesan WAG -->
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">CONTOH PESAN DI GRUP WA</div>
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
                            <strong>Tips agar iklan tampil lebih baik:</strong><br>
                            Sertakan harga yang jelas, kondisi produk (baru/bekas), dan lokasi kota. Foto yang terang dan produk terlihat jelas = lebih banyak calon pembeli.
                        </div>
                    </div>

                    <div class="info-box green mt-2">
                        <i class="bi bi-lightning-charge-fill text-em"></i>
                        <div>
                            <strong>Iklan lama otomatis diganti:</strong> Jika kamu kirim produk yang sama lagi, sistem otomatis menghapus iklan lama dan menampilkan yang terbaru.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Jenis konten yang didukung -->
            <div class="mt-4">
                <div class="fw-700 mb-2" style="font-size:.88rem;color:#374151;">Jenis konten yang didukung di grup:</div>
                <div class="row g-2">
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">📸</div>
                            <strong>Foto</strong><br>
                            <span style="color:#6b7280;">JPG / PNG</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">🎥</div>
                            <strong>Video</strong><br>
                            <span style="color:#6b7280;">MP4 / MOV</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">📝</div>
                            <strong>Teks saja</strong><br>
                            <span style="color:#6b7280;">Iklan baris</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-white rounded-3 border" style="font-size:.82rem;">
                            <div style="font-size:1.5rem;">📍</div>
                            <strong>Lokasi</strong><br>
                            <span style="color:#6b7280;">Via WA Pribadi</span>
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
            <div class="section-label">WA Pribadi (DM)</div>
            <div class="section-title">Interaksi dengan Bot AI Lewat WA Pribadi</div>
            <p class="section-lead mb-4">Bot AI siap membantu 24 jam — tidak perlu hafal perintah khusus, cukup chat natural dalam Bahasa Indonesia.</p>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="info-box blue mb-3">
                        <i class="bi bi-info-circle-fill text-blue"></i>
                        <div>
                            Setelah bergabung di grup, Bot akan kirim pesan perkenalan ke WA pribadimu. Balas pesan itu — itulah cara kamu mulai berinteraksi dengan Bot AI.
                        </div>
                    </div>

                    <div class="info-box green">
                        <i class="bi bi-shield-check-fill text-em"></i>
                        <div>
                            <strong>Privasi terjaga:</strong> Semua percakapan di WA Pribadi hanya antara kamu dan Bot. Anggota grup lain tidak bisa melihat riwayat chat ini.
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">CONTOH PERCAKAPAN</div>
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
            <div class="section-label">Panduan Perintah</div>
            <div class="section-title">Apa Saja yang Bisa Kamu Minta ke Bot?</div>
            <p class="section-lead mb-4">Tulis pesan natural dalam Bahasa Indonesia — Bot AI memahami maksudmu tanpa perlu perintah kaku.</p>

            <!-- Kategori 1: Iklan -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--em-xlight);color:var(--em);padding:.2rem .6rem;border-radius:8px;">🛍️ Kelola Iklan</span>
            </h5>
            <div class="mb-4">
                <div class="cmd-card">
                    <div class="cmd-icon bg-em-xs text-em">📸</div>
                    <div>
                        <div class="cmd-title">Buat Iklan Baru via Bot</div>
                        <div class="cmd-desc">Kirim foto produk ke WA Pribadi Bot — AI otomatis buat draft iklan profesional, kamu tinggal review & setuju.</div>
                        <div class="cmd-example">Ketik: "buat iklan" atau langsung kirim foto</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-amber text-amber">✏️</div>
                    <div>
                        <div class="cmd-title">Edit Iklan</div>
                        <div class="cmd-desc">Ubah judul, harga, deskripsi, atau lokasi iklanmu. Sebutkan ID iklan dan perubahan yang diinginkan.</div>
                        <div class="cmd-example">Ketik: "edit iklan #142" atau "ubah harga iklan 142 jadi 30rb"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-blue text-blue">📋</div>
                    <div>
                        <div class="cmd-title">Lihat Iklanku</div>
                        <div class="cmd-desc">Tampilkan daftar semua iklan aktif milikmu lengkap dengan ID, judul, dan status.</div>
                        <div class="cmd-example">Ketik: "iklan saya" atau "lihat iklanku"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--rose-light);color:var(--rose);">✅</div>
                    <div>
                        <div class="cmd-title">Tandai Terjual</div>
                        <div class="cmd-desc">Nonaktifkan iklan setelah produk berhasil terjual. Iklan hilang dari website secara otomatis.</div>
                        <div class="cmd-example">Ketik: "terjual #142" atau "laku iklan 142"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--purple-light);color:var(--purple);">🔄</div>
                    <div>
                        <div class="cmd-title">Aktifkan Kembali Iklan</div>
                        <div class="cmd-desc">Aktifkan lagi iklan yang sebelumnya telah ditandai terjual atau dinonaktifkan.</div>
                        <div class="cmd-example">Ketik: "aktifkan #142" atau "aktifkan kembali iklan 142"</div>
                    </div>
                </div>
            </div>

            <!-- Kategori 2: Cari Produk -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--blue-light);color:var(--blue);padding:.2rem .6rem;border-radius:8px;">🔍 Cari Produk & Penjual</span>
            </h5>
            <div class="mb-4">
                <div class="cmd-card">
                    <div class="cmd-icon bg-blue text-blue">🛒</div>
                    <div>
                        <div class="cmd-title">Cari Produk</div>
                        <div class="cmd-desc">Cari produk yang tersedia di marketplace. Bot akan tampilkan produk relevan beserta harga dan link.</div>
                        <div class="cmd-example">Ketik: "ada jual kerudung?" atau "cari madu asli"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-em-xs text-em">👤</div>
                    <div>
                        <div class="cmd-title">Cari Penjual</div>
                        <div class="cmd-desc">Temukan penjual berdasarkan kategori atau nama produk yang mereka jual.</div>
                        <div class="cmd-example">Ketik: "cari penjual makanan" atau "siapa yang jual batik?"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-amber text-amber">📂</div>
                    <div>
                        <div class="cmd-title">Lihat Kategori</div>
                        <div class="cmd-desc">Tampilkan semua kategori produk yang tersedia di marketplace.</div>
                        <div class="cmd-example">Ketik: "kategori apa saja?" atau "daftar kategori"</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--purple-light);color:var(--purple);">📍</div>
                    <div>
                        <div class="cmd-title">Cari Produk Terdekat</div>
                        <div class="cmd-desc">Bagikan lokasi WA kamu → Bot cari produk di sekitar lokasimu secara otomatis.</div>
                        <div class="cmd-example">Kirim <strong>Lokasi</strong> WhatsApp → pilih opsi <em>"Cari produk di sekitar lokasi ini"</em></div>
                    </div>
                </div>
            </div>

            <!-- Kategori 3: Profil & Akun -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--purple-light);color:var(--purple);padding:.2rem .6rem;border-radius:8px;">👤 Profil & Akun</span>
            </h5>
            <div class="mb-4">
                <div class="cmd-card">
                    <div class="cmd-icon" style="background:var(--purple-light);color:var(--purple);">🗂️</div>
                    <div>
                        <div class="cmd-title">Update Lokasi Bisnis</div>
                        <div class="cmd-desc">Bagikan lokasi WA → pilih opsi update lokasi bisnis agar pembeli tahu kamu berjualan dari mana.</div>
                        <div class="cmd-example">Kirim <strong>Lokasi</strong> WhatsApp → pilih opsi <em>"Update lokasi bisnis saya"</em></div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-blue text-blue">🪪</div>
                    <div>
                        <div class="cmd-title">Scan KTP (Verifikasi Identitas)</div>
                        <div class="cmd-desc">Kirim foto KTP → Bot AI membaca dan mengisi data profilmu secara otomatis (nama, alamat, dll).</div>
                        <div class="cmd-example">Ketik: "scan KTP" → lalu kirim foto KTP</div>
                    </div>
                </div>
                <div class="cmd-card">
                    <div class="cmd-icon bg-em-xs text-em">📞</div>
                    <div>
                        <div class="cmd-title">Hubungi Admin</div>
                        <div class="cmd-desc">Bot meneruskan pesanmu ke admin untuk masalah yang perlu penanganan manusia.</div>
                        <div class="cmd-example">Ketik: "hubungi admin" atau "minta bantuan admin"</div>
                    </div>
                </div>
            </div>

            <!-- Kategori 4: Lainnya -->
            <h5 class="fw-800 mb-3" style="color:#111827;font-size:1rem;">
                <span style="background:var(--amber-light);color:#92400e;padding:.2rem .6rem;border-radius:8px;">ℹ️ Lainnya</span>
            </h5>
            <div class="mb-2">
                <div class="cmd-card">
                    <div class="cmd-icon bg-amber text-amber">❓</div>
                    <div>
                        <div class="cmd-title">Bantuan / Daftar Perintah</div>
                        <div class="cmd-desc">Tampilkan daftar lengkap fitur dan cara penggunaan bot langsung di chat.</div>
                        <div class="cmd-example">Ketik: "bantuan" atau "help"</div>
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
            <div class="section-label">Untuk Pembeli</div>
            <div class="section-title">Cara Membeli Produk</div>
            <p class="section-lead mb-4">MarketplaceJamaah adalah platform listing — transaksi dilakukan langsung antara pembeli dan penjual.</p>

            <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num bg-em-xs text-em mx-auto" style="font-size:1.5rem;width:52px;height:52px;">🔍</div>
                        <div class="step-title mt-2">Cari Produk</div>
                        <div class="step-body">Browse website atau chat bot dengan kata kunci produk yang dicari.</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num bg-amber mx-auto" style="font-size:1.5rem;width:52px;height:52px;">📄</div>
                        <div class="step-title mt-2">Lihat Detail</div>
                        <div class="step-body">Klik produk untuk melihat foto lengkap, deskripsi, harga, dan info penjual.</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num bg-blue text-blue mx-auto" style="font-size:1.5rem;width:52px;height:52px;">💬</div>
                        <div class="step-title mt-2">Hubungi Penjual</div>
                        <div class="step-body">Klik tombol "Chat Penjual" — otomatis terhubung ke WA penjual.</div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="step-card text-center">
                        <div class="step-num" style="background:var(--purple-light);color:var(--purple);font-size:1.5rem;width:52px;height:52px;margin:auto;">🤝</div>
                        <div class="step-title mt-2">Sepakati & Bayar</div>
                        <div class="step-body">Negosiasi harga dan metode pengiriman langsung dengan penjual di WA.</div>
                    </div>
                </div>
            </div>

            <div class="info-box amber mt-4">
                <i class="bi bi-shield-exclamation text-amber"></i>
                <div>
                    <strong>Penting:</strong> MarketplaceJamaah <strong>bukan</strong> perantara transaksi. Kami menyediakan platform listing saja. Selalu berhati-hati dalam bertransaksi — pastikan kamu kenal atau percaya kepada penjual, terutama untuk transaksi bernilai besar.
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         SECTION 6 — CONTOH PERCAKAPAN LENGKAP
    ═══════════════════════════════════════════════════════════ -->
    <div class="row justify-content-center mb-5">
        <div class="col-lg-10">
            <div class="section-label">Contoh Nyata</div>
            <div class="section-title">Sesi Lengkap Buat Iklan via WA Pribadi</div>
            <p class="section-lead mb-4">Kamu bisa buat iklan yang lebih kaya lewat WA Pribadi menggunakan fitur Ad Builder AI.</p>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">SESI AD BUILDER — LANGKAH DEMI LANGKAH</div>
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
                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">CONTOH EDIT IKLAN</div>
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

                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">CONTOH TANDAI TERJUAL</div>
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

                    <div class="mb-2" style="font-size:.8rem;font-weight:700;color:#6b7280;">CONTOH CARI PRODUK</div>
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
            <div class="section-title">Pertanyaan yang Sering Ditanyakan</div>
            <p class="section-lead mb-4">Temukan jawaban untuk pertanyaan umum seputar MarketplaceJamaah.</p>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Apakah harus bayar untuk pasang iklan?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    <strong>Tidak ada biaya sama sekali.</strong> MarketplaceJamaah gratis untuk semua anggota komunitas jamaah. Pasang iklan sebanyak yang kamu mau tanpa biaya.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Bagaimana cara bergabung di grup WhatsApp?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    Hubungi admin marketplace untuk mendapatkan link undangan grup. Setelah bergabung, Bot AI akan secara otomatis menyapa kamu lewat WA pribadi dan membimbing proses pendaftaran.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Berapa lama iklan saya akan tayang?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    Iklan tayang selama produk masih tersedia. Iklan akan otomatis hilang jika kamu menandainya sebagai terjual, dihapus dari grup, atau jika kamu keluar dari grup. Tidak ada batas waktu tayangnya selama statusnya aktif.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Apakah bisa pasang iklan tanpa foto?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    Bisa! Kirim teks saja di grup → iklan akan tampil sebagai <em>iklan baris</em> di bagian bawah website. Tapi iklan dengan foto/video punya tampilan yang jauh lebih menarik dan lebih mudah ditemukan oleh pembeli.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Bot tidak merespons pesan saya — apa yang harus dilakukan?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    Coba kirim pesan <code>bantuan</code> atau <code>halo</code> ke nomor Bot. Jika masih tidak merespons, hubungi admin. Pastikan kamu mengirim DM ke nomor Bot, bukan ke grup. Bot hanya aktif membalas pesan di WA Pribadi, bukan di dalam grup.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Apakah MarketplaceJamaah memproses pembayaran?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    Tidak. MarketplaceJamaah adalah platform listing — kami hanya mempertemukan penjual dan pembeli. Semua transaksi dilakukan secara mandiri antara pembeli dan penjual lewat WhatsApp atau cara yang mereka sepakati.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Bisa pasang lebih dari satu iklan?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    Tentu! Kamu bisa pasang iklan sebanyak yang kamu inginkan. Setiap pesan foto/video/teks baru yang kamu kirim di grup akan diproses sebagai iklan terpisah. Untuk produk yang sama, sistem otomatis mengganti iklan lama dengan yang baru.
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-q" onclick="toggleFaq(this)">
                    Apakah semua produk boleh dijual?
                    <i class="bi bi-plus-lg faq-icon"></i>
                </div>
                <div class="faq-a">
                    MarketplaceJamaah khusus untuk produk <strong>halal</strong>. Dilarang menjual produk yang mengandung riba, produk haram (alkohol, rokok, dll), produk ilegal, atau produk yang menipu. Admin berhak menghapus iklan dan memblokir pengguna yang melanggar ketentuan.
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
                <h3 class="fw-900 mb-2" style="color:#fff;">Siap Bergabung?</h3>
                <p style="color:#a7f3d0;max-width:480px;margin:0 auto 1.5rem;font-size:.95rem;">
                    Gabung komunitas jamaah yang jual beli dengan mudah, amanah, dan berkah lewat WhatsApp.
                </p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="{{ route('landing') }}" class="btn-nav-primary">
                        <i class="bi bi-shop"></i> Lihat Produk
                    </a>
                    <a href="{{ route('marketing-tools') }}" style="background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.25);border-radius:10px;font-size:.82rem;font-weight:700;padding:.48rem 1.2rem;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;">
                        <i class="bi bi-stars"></i> Lihat Semua Fitur
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
            <div>© {{ date('Y') }} MarketplaceJamaah · Jual beli komunitas halal Indonesia</div>
            <div class="d-flex gap-3 flex-wrap">
                <a href="{{ route('landing') }}">Beranda</a>
                <a href="{{ route('marketing-tools') }}">Fitur</a>
                <a href="{{ route('panduan') }}">Panduan</a>
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
