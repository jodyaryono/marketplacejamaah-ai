<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarketplaceJamaah — Jual Beli Komunitas WhatsApp</title>
    <meta name="description" content="Temukan ribuan produk dari komunitas jamaah WhatsApp. Jual beli mudah, aman, dan terpercaya.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --em: #059669;
            --em-dark: #047857;
            --em-light: #a7f3d0;
            --em-xlight: #ecfdf5;
            --em-mid: #34d399;
            --em-glow: rgba(5,150,105,.35);
            --amber: #f59e0b;
            --amber-dark: #d97706;
            --amber-light: #fef3c7;
        }
        * { box-sizing: border-box; }
        body { background: #f0fdf8; color: #111827; font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; }

        /* ── Navbar ─────────────────── */
        .site-nav {
            background: rgba(255,255,255,.92);
            backdrop-filter: blur(12px);
            border-bottom: 1.5px solid rgba(167,243,208,.5);
            padding: .7rem 0;
            position: sticky; top: 0; z-index: 100;
            box-shadow: 0 2px 16px rgba(5,150,105,.1);
        }
        .brand-logo {
            width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px rgba(5,150,105,.45), inset 0 1px 0 rgba(255,255,255,.2);
            flex-shrink: 0;
        }
        .brand-logo i { color: #fff; font-size: 1.15rem; }
        .brand-name { font-size: 1.1rem; font-weight: 800; color: #111827; line-height: 1.2; }
        .brand-name span { color: var(--em); }
        .brand-sub { font-size: .65rem; color: #6b7280; font-weight: 500; }
        .btn-dashboard {
            background: linear-gradient(135deg, #059669, #10b981, #34d399);
            color: #fff; border: none; border-radius: 10px;
            font-size: .82rem; font-weight: 700; padding: .48rem 1.2rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
            box-shadow: 0 3px 12px rgba(5,150,105,.45);
            transition: all .2s;
        }
        .btn-dashboard:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(5,150,105,.5); }
        .btn-login-admin {
            background: #fff; color: #374151; border: 1.5px solid #d1d5db;
            border-radius: 10px; font-size: .82rem; font-weight: 600; padding: .4rem 1rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: .35rem;
            transition: all .15s;
        }
        .btn-login-admin {
            background: var(--em-xlight); color: var(--em-dark); border: 1.5px solid var(--em-light);
            border-radius: 10px; font-size: .82rem; font-weight: 700; padding: .42rem 1.1rem;
            text-decoration: none; display: inline-flex; align-items: center; gap: .38rem;
            transition: all .18s;
        }
        .btn-login-admin:hover { background: var(--em); color: #fff; border-color: var(--em); box-shadow: 0 4px 14px rgba(5,150,105,.4); }

        /* ── Hero Carousel ───────────── */
        .hero-carousel-section {
            height: 620px; position: relative; overflow: hidden; background: #042f24;
        }
        .hero-carousel-section .carousel,
        .hero-carousel-section .carousel-inner,
        .hero-carousel-section .carousel-item { height: 100%; }
        .hero-slide-img {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%; object-fit: cover; object-position: center;
            transition: transform 10s ease;
        }
        .hero-carousel-section .carousel-item.active .hero-slide-img { transform: scale(1.06); }
        .hero-slide-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(110deg,
                rgba(2,24,16,.96) 0%,
                rgba(4,47,36,.9) 28%,
                rgba(4,47,36,.62) 55%,
                rgba(0,0,0,.08) 100%);
        }
        .hero-slide-content {
            position: absolute; inset: 0; z-index: 5;
            display: flex; align-items: center; padding: 2.5rem 0 4rem;
        }
        /* Eyebrow */
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: .45rem;
            background: rgba(110,231,183,.12); border: 1.5px solid rgba(110,231,183,.4);
            color: #6ee7b7; border-radius: 100px; padding: .3rem 1.05rem;
            font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
            backdrop-filter: blur(8px); margin-bottom: 1.25rem;
        }
        .hero-carousel-section h1 {
            font-size: 3.2rem; font-weight: 800; line-height: 1.1; color: #fff;
            letter-spacing: -.03em; margin-bottom: 1rem;
        }
        .hero-carousel-section h1 em {
            font-style: normal; color: #6ee7b7;
            text-shadow: 0 0 40px rgba(110,231,183,.4);
        }
        .hero-lead {
            font-size: 1rem; color: rgba(255,255,255,.75); font-weight: 500;
            max-width: 440px; line-height: 1.72; margin-bottom: 1.6rem;
        }
        .hero-stats { display: flex; flex-wrap: wrap; gap: .55rem; margin-bottom: 1.9rem; }
        .hero-stat-item {
            display: inline-flex; align-items: center; gap: .38rem;
            background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(8px); border-radius: 100px;
            padding: .36rem 1.05rem; font-size: .8rem; font-weight: 600; color: #fff;
        }
        .hero-stat-item i { color: #6ee7b7; font-size: .82rem; }
        .hero-cta-wrap { display: flex; flex-wrap: wrap; gap: .8rem; }
        .hero-btn-primary {
            display: inline-flex; align-items: center; gap: .42rem;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; text-decoration: none; border-radius: 12px;
            font-size: .92rem; font-weight: 700; padding: .75rem 1.7rem;
            box-shadow: 0 4px 22px rgba(5,150,105,.55); transition: all .22s;
        }
        .hero-btn-primary:hover { color: #fff; transform: translateY(-2px); box-shadow: 0 8px 30px rgba(5,150,105,.65); }
        .hero-btn-outline {
            display: inline-flex; align-items: center; gap: .42rem;
            background: rgba(255,255,255,.1); border: 1.5px solid rgba(255,255,255,.3);
            color: #fff; text-decoration: none; border-radius: 12px;
            font-size: .92rem; font-weight: 600; padding: .72rem 1.55rem;
            backdrop-filter: blur(8px); transition: all .22s;
        }
        .hero-btn-outline:hover { color: #fff; background: rgba(255,255,255,.22); border-color: rgba(255,255,255,.55); }
        /* Product info card in hero (right column) */
        .hero-product-card-wrap { position: relative; z-index: 10; }
        .hero-product-card {
            background: rgba(5,20,14,.72); backdrop-filter: blur(26px);
            -webkit-backdrop-filter: blur(26px);
            border: 1.5px solid rgba(110,231,183,.28); border-radius: 22px; overflow: hidden;
            box-shadow: 0 28px 70px rgba(0,0,0,.45), 0 0 0 1px rgba(110,231,183,.08);
        }
        .hero-product-img {
            width: 100%; height: 195px; object-fit: cover; display: block;
            border-bottom: 1px solid rgba(110,231,183,.15);
        }
        .hero-product-body { padding: 1.2rem 1.4rem 1.4rem; }
        .hero-product-cat {
            font-size: .63rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
            color: #6ee7b7; background: rgba(110,231,183,.12);
            border: 1px solid rgba(110,231,183,.32);
            border-radius: 6px; padding: .18rem .52rem; display: inline-block; margin-bottom: .6rem;
        }
        .hero-product-title {
            font-size: 1.02rem; font-weight: 800; color: #fff; line-height: 1.42;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
            margin-bottom: .5rem;
        }
        .hero-product-price {
            font-size: 1.22rem; font-weight: 800; color: #6ee7b7; margin-bottom: .5rem;
            text-shadow: 0 0 20px rgba(110,231,183,.3);
        }
        .hero-product-seller {
            font-size: .75rem; color: rgba(255,255,255,.56);
            display: flex; align-items: center; gap: .35rem; margin-bottom: .9rem;
        }
        /* Carousel controls */
        .hero-carousel-section .carousel-indicators { bottom: 1.8rem; }
        .hero-carousel-section .carousel-indicators button {
            width: 28px; height: 4px; border-radius: 3px; border: none;
            background: rgba(255,255,255,.32); opacity: 1; transition: all .32s;
        }
        .hero-carousel-section .carousel-indicators button.active {
            background: #6ee7b7; width: 46px;
        }
        .hero-carousel-section .carousel-control-prev,
        .hero-carousel-section .carousel-control-next { width: 44px; }

        /* ── Video thumbnail in product cards ── */
        .product-img-wrap video {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%; object-fit: cover; object-position: center;
        }
        .video-play-badge {
            position: absolute; bottom: .5rem; left: .5rem; z-index: 2;
            background: rgba(0,0,0,.6); color: #fff; border-radius: 100px;
            padding: .2rem .6rem; font-size: .7rem; font-weight: 700;
            display: flex; align-items: center; gap: .25rem;
            pointer-events: none;
        }

        /* ── Search card ─────────────── */
        .search-card {
            background: #fff;
            border: 2px solid var(--em-light);
            border-radius: 22px;
            box-shadow: 0 12px 50px rgba(5,150,105,.18), 0 2px 8px rgba(0,0,0,.06);
            padding: 1.6rem 1.9rem;
            margin-top: -2.8rem;
            position: relative; z-index: 20;
        }
        .search-card .form-control, .search-card .input-group-text {
            border-color: #d1d5db; font-size: .9rem; color: #111827;
        }
        .search-card .form-control:focus { border-color: var(--em); box-shadow: 0 0 0 3px rgba(5,150,105,.15); }
        .btn-search {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border: none; border-radius: 10px;
            font-weight: 700; padding: .55rem 1.7rem; font-size: .88rem;
            box-shadow: 0 4px 14px rgba(5,150,105,.4);
            transition: all .2s; white-space: nowrap;
        }
        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(5,150,105,.5); color: #fff; }
        .btn-reset {
            background: #fff; border: 1.5px solid #e5e7eb; color: #6b7280;
            border-radius: 10px; font-weight: 600; font-size: .85rem;
            padding: .5rem 1.1rem; transition: all .15s; white-space: nowrap;
        }
        .btn-reset:hover { border-color: #ef4444; color: #ef4444; }

        /* ── Category pills ──────────── */
        .cat-pill {
            display: inline-block; padding: .32rem .9rem; border-radius: 100px;
            background: #f3f4f6; color: #6b7280; border: 1.5px solid transparent;
            font-size: .78rem; font-weight: 600; text-decoration: none; transition: all .15s;
            white-space: nowrap;
        }
        .cat-pill:hover { background: var(--em-xlight); color: var(--em); border-color: var(--em-light); }
        .cat-pill.active {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border-color: transparent; font-weight: 700;
            box-shadow: 0 2px 8px rgba(5,150,105,.35);
        }
        .cat-pills-wrap { display: flex; gap: .4rem; overflow-x: auto; padding-bottom: 2px; scrollbar-width: none; }
        .cat-pills-wrap::-webkit-scrollbar { display: none; }

        /* ── Section header ──────────── */
        .section-label { font-size: .82rem; color: #6b7280; font-weight: 500; }
        .section-label strong { color: #111827; font-weight: 700; }

        /* ── Product cards ───────────── */
        .product-card {
            background: #fff;
            border: 1.5px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            transition: box-shadow .25s, transform .25s, border-color .25s;
            height: 100%;
            display: flex; flex-direction: column;
            box-shadow: 0 2px 8px rgba(0,0,0,.05);
        }
        .product-card:hover {
            box-shadow: 0 16px 50px rgba(5,150,105,.2), 0 4px 16px rgba(0,0,0,.08);
            transform: translateY(-5px);
            border-color: var(--em-mid);
        }
        .product-img-wrap {
            width: 100%; position: relative; padding-top: 75%; /* 4:3 ratio */
            overflow: hidden; background: var(--em-xlight);
        }
        .product-img-wrap img {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%; object-fit: cover; object-position: center; transition: transform .4s;
        }
        .product-card:hover .product-img-wrap img { transform: scale(1.08); }
        .product-img-placeholder {
            position: absolute; top: 0; left: 0;
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #ecfdf5, #a7f3d0 50%, #d1fae5);
        }
        .product-img-placeholder i { font-size: 3rem; color: var(--em-mid); }
        .product-body { padding: .9rem 1rem; flex: 1; display: flex; flex-direction: column; gap: .25rem; }
        .product-category {
            font-size: .67rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
            color: var(--em-dark); background: var(--em-xlight); border-radius: 6px;
            padding: .2rem .55rem; display: inline-block; border: 1px solid var(--em-light);
        }
        .product-title {
            font-size: .88rem; font-weight: 700; color: #111827;
            overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2;
            -webkit-box-orient: vertical; line-height: 1.4; margin-top: .1rem;
        }
        .product-price {
            font-size: 1.08rem; font-weight: 800;
            background: linear-gradient(90deg, #059669, #10b981);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .product-price.negotiable {
            background: linear-gradient(90deg, #d97706, #f59e0b);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .product-seller {
            font-size: .73rem; color: #9ca3af;
            display: flex; align-items: center; gap: .3rem; margin-top: auto;
            padding-top: .5rem; border-top: 1.5px dashed #d1fae5;
        }
        .product-seller i { color: var(--em-mid); }
        .product-footer {
            padding: .65rem .9rem;
            border-top: 1px solid #f0fdf4;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        }
        .btn-wa {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; border: none; border-radius: 9px;
            font-size: .8rem; font-weight: 700;
            padding: .48rem .9rem; text-decoration: none; display: inline-flex;
            align-items: center; gap: .35rem; width: 100%; justify-content: center;
            transition: all .2s; box-shadow: 0 3px 10px rgba(5,150,105,.35);
        }
        .btn-wa:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(5,150,105,.5); color: #fff; }

        /* ── Feature strip ───────────── */
        .feature-strip {
            background: linear-gradient(135deg, #fff, #f0fdf4);
            border: 2px solid var(--em-light);
            border-radius: 18px; padding: 1.25rem 1.5rem;
            margin-bottom: 1.75rem;
            box-shadow: 0 4px 20px rgba(5,150,105,.08);
        }
        .feature-item {
            display: flex; align-items: center; gap: .55rem;
            font-size: .83rem; color: #374151; font-weight: 600;
        }
        .feature-item i {
            font-size: 1.3rem;
            background: linear-gradient(135deg, #059669, #34d399);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Empty state ─────────────── */
        .empty-state { text-align: center; padding: 4rem 1rem; }
        .empty-state .empty-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--em-xlight), #a7f3d0);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 20px rgba(5,150,105,.18);
        }
        .empty-state .empty-icon i { font-size: 2.2rem; color: var(--em); }
        .empty-state h5 { color: #374151; font-weight: 700; }
        .empty-state p { color: #9ca3af; font-size: .88rem; }

        /* ── Footer ──────────────────── */
        .site-footer {
            background: linear-gradient(135deg, #064e3b, #065f46);
            border-top: none;
            padding: 2.5rem 0; margin-top: 4rem; text-align: center;
        }
        .site-footer .footer-logo { font-size: 1.05rem; font-weight: 800; color: #fff; }
        .site-footer .footer-logo span { color: #6ee7b7; }
        .site-footer p { font-size: .8rem; color: rgba(255,255,255,.55); margin: .3rem 0 0; }
        .site-footer a { color: #6ee7b7; text-decoration: none; font-weight: 600; }
        .site-footer a:hover { text-decoration: underline; }

        /* ── Pagination ──────────────── */
        .pagination .page-link { color: var(--em); border-color: #e5e7eb; font-weight: 600; }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #059669, #10b981);
            border-color: var(--em); color: #fff;
            box-shadow: 0 2px 8px rgba(5,150,105,.4);
        }
        .pagination .page-link:hover { background: var(--em-xlight); color: var(--em-dark); border-color: var(--em-light); }

        /* ── Iklan Baris ───────────────── */
        .iklan-baris-list { display: flex; flex-direction: column; gap: .5rem; }
        .iklan-baris-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto auto;
            grid-template-areas: "main price wa";
            align-items: center;
            column-gap: 1rem;
            background: #fff; border: 1.5px solid #e5e7eb; border-radius: 13px;
            padding: .72rem 1rem; text-decoration: none; color: inherit;
            transition: border-color .18s, box-shadow .18s;
        }
        .iklan-baris-row:hover { border-color: var(--em-mid); box-shadow: 0 4px 18px rgba(5,150,105,.12); color: inherit; }
        .iklan-baris-main { grid-area: main; min-width: 0; display: flex; flex-direction: column; gap: .18rem; }
        .iklan-baris-cat {
            display: inline-block; align-self: flex-start;
            font-size: .6rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
            color: var(--em-dark); background: var(--em-xlight); border: 1px solid var(--em-light);
            border-radius: 6px; padding: .14rem .5rem; white-space: nowrap; max-width: 100%;
            overflow: hidden; text-overflow: ellipsis;
        }
        .iklan-baris-title { font-size: .9rem; font-weight: 700; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.3; }
        .iklan-baris-desc { font-size: .75rem; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .iklan-baris-meta { font-size: .7rem; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; gap: .35rem; margin-top: .12rem; }
        .iklan-baris-price {
            grid-area: price;
            font-size: .92rem; font-weight: 800; white-space: nowrap; text-align: right;
            max-width: 160px; overflow: hidden; text-overflow: ellipsis;
            background: linear-gradient(90deg,#059669,#10b981);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .iklan-baris-wa {
            grid-area: wa;
            display: inline-flex; align-items: center; gap: .3rem;
            background: linear-gradient(135deg, #059669, #10b981); color: #fff;
            border-radius: 8px; font-size: .74rem; font-weight: 700; padding: .4rem .85rem;
            text-decoration: none; box-shadow: 0 2px 8px rgba(5,150,105,.28); transition: all .18s;
            white-space: nowrap;
        }
        .iklan-baris-wa:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(5,150,105,.45); }
        .iklan-baris-wa--detail { background: #fff; color: var(--em-dark); border: 1.5px solid var(--em-light); box-shadow: none; }
        .iklan-baris-wa--detail:hover { color: var(--em-dark); background: var(--em-xlight); box-shadow: 0 2px 10px rgba(5,150,105,.15); }
        @media (max-width: 576px) {
            .iklan-baris-row { grid-template-columns: minmax(0, 1fr) auto; grid-template-areas: "main wa" "price price"; column-gap: .6rem; row-gap: .35rem; }
            .iklan-baris-price { text-align: left; font-size: .85rem; }
            .iklan-baris-desc, .iklan-baris-meta { display: none; }
        }
        .section-divider {
            display: flex; align-items: center; gap: 1rem; margin: 2.5rem 0 1.2rem;
        }
        .section-divider-title {
            font-size: 1.05rem; font-weight: 800; color: #111827; white-space: nowrap;
            display: flex; align-items: center; gap: .45rem;
        }
        .section-divider-line { flex: 1; height: 1.5px; background: linear-gradient(90deg,#d1fae5,transparent); }

        /* ── WhatsApp Join Banner ─────── */
        .wa-join-banner {
            background: linear-gradient(135deg, #064e3b 0%, #059669 55%, #10b981 100%);
            border-radius: 18px; padding: 1.5rem 1.8rem; color: #fff;
            box-shadow: 0 8px 30px rgba(5,150,105,.35); border: 1px solid rgba(110,231,183,.2);
        }
        .wa-join-qr-wrap {
            background: #fff; border-radius: 12px; padding: 8px;
            display: inline-flex; box-shadow: 0 4px 16px rgba(0,0,0,.22); flex-shrink: 0;
        }
        .wa-join-label {
            font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
            color: #6ee7b7; margin-bottom: .4rem; display: flex; align-items: center; gap: .35rem;
        }
        .wa-join-title { font-size: 1.15rem; font-weight: 800; color: #fff; margin-bottom: .35rem; }
        .wa-join-desc { font-size: .83rem; color: rgba(255,255,255,.82); line-height: 1.6; margin-bottom: .95rem; max-width: 500px; }
        .wa-join-btn {
            display: inline-flex; align-items: center; gap: .45rem;
            background: #fff; color: #059669; font-weight: 800; font-size: .88rem;
            border-radius: 10px; padding: .6rem 1.5rem; text-decoration: none;
            box-shadow: 0 4px 16px rgba(0,0,0,.18); transition: all .2s;
        }
        .wa-join-btn:hover { background: #ecfdf5; color: #047857; transform: translateY(-2px); box-shadow: 0 6px 22px rgba(0,0,0,.25); }
        .wa-join-link { font-size: .74rem; color: rgba(255,255,255,.55); word-break: break-all; margin-top: .45rem; }
        @media (max-width: 576px) {
            .wa-join-banner { padding: 1.2rem 1.1rem; }
            .wa-join-title { font-size: .98rem; }
        }

        /* ── Responsive ──────────────── */
        @media (max-width: 768px) {
            .hero-carousel-section { height: 560px; }
            .hero-carousel-section h1 { font-size: 1.9rem; }
            .hero-lead { font-size: .88rem; max-width: 100%; }
            .hero-cta-wrap { gap: .5rem; }
            .hero-btn-primary, .hero-btn-outline { font-size: .83rem; padding: .62rem 1.15rem; }
            .hero-slide-content { padding: 2rem 0 3.5rem; }
            .search-card { border-radius: 18px; padding: 1.1rem 1.1rem; margin-top: -1.5rem; }
            .feature-strip { display: none; }
        }
    </style>
</head>
<body>

{{-- Navbar --}}
<nav class="site-nav">
    <div class="container d-flex align-items-center justify-content-between">
        <a class="d-flex align-items-center gap-2 text-decoration-none" href="{{ url('/') }}">
            <div class="brand-logo"><i class="bi bi-shop-window"></i></div>
            <div>
                <div class="brand-name">Marketplace<span>Jamaah</span></div>
                <div class="brand-sub">AI-powered WhatsApp Marketplace</div>
            </div>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('panduan') }}" class="btn-login-admin d-none d-md-flex">
                <i class="bi bi-book"></i> Panduan
            </a>
            <a href="{{ route('marketing-tools') }}" class="btn-login-admin d-none d-lg-flex">
                <i class="bi bi-stars"></i> Fitur
            </a>
            @auth
                <a href="{{ route('dashboard') }}" class="btn-dashboard">
                    <i class="bi bi-speedometer2"></i>Dashboard
                </a>
            @endauth
        </div>
    </div>
</nav>

{{-- Hero Carousel --}}
<section class="hero-carousel-section">
    @if($heroListings->isNotEmpty())
    <div id="heroCarousel" class="carousel slide carousel-fade h-100" data-bs-ride="carousel" data-bs-interval="4500">
        <div class="carousel-inner h-100">
            @foreach($heroListings as $hl)
            @php
                $hlPhone = preg_replace('/\D/', '', $hl->contact?->phone_number ?? '');
                if (str_starts_with($hlPhone, '0')) $hlPhone = '62' . substr($hlPhone, 1);
                elseif ($hlPhone && !str_starts_with($hlPhone, '62')) $hlPhone = '62' . $hlPhone;
                if (strlen($hlPhone) >= 15 && str_starts_with($hlPhone, '2500')) $hlPhone = null;
                $hlText = urlencode('Halo, saya tertarik dengan produk "' . $hl->title . '". Apakah masih tersedia?');
                $hlLink = $hlPhone ? 'https://wa.me/' . $hlPhone . '?text=' . $hlText : null;
            @endphp
            <div class="carousel-item h-100 {{ $loop->first ? 'active' : '' }}">
                @php $hlIsVideo = preg_match('/\.(mp4|mov|webm)$/i', $hl->media_urls[0] ?? ''); @endphp
                @if($hlIsVideo)
                    <video autoplay muted loop playsinline class="hero-slide-img"
                           style="position:absolute;top:0;left:0;object-fit:cover;">
                        <source src="{{ $hl->media_urls[0] }}" type="video/mp4">
                    </video>
                @else
                    <img src="{{ $hl->media_urls[0] }}" class="hero-slide-img" alt="{{ $hl->title }}"
                         loading="{{ $loop->first ? 'eager' : 'lazy' }}">
                @endif
                <div class="hero-slide-overlay"></div>
                <div class="hero-slide-content">
                    <div class="container">
                        <div class="row align-items-center g-5">
                            <div class="col-lg-6 col-12">
                                <div class="hero-eyebrow">
                                    <i class="bi bi-robot"></i>&nbsp;AI &middot; WhatsApp Marketplace
                                </div>
                                <h1>Semua Produk<br><em>Komunitas Jamaah</em><br>dalam Satu Tempat</h1>
                                <p class="hero-lead">Produk dari grup WhatsApp jamaah dideteksi &amp; dikurasi otomatis oleh AI &mdash; mudah, cepat, dan terpercaya.</p>
                                <div class="hero-stats">
                                    <span class="hero-stat-item"><i class="bi bi-tag-fill"></i>&nbsp;{{ $totalActive }} produk aktif</span>
                                    <span class="hero-stat-item"><i class="bi bi-grid-fill"></i>&nbsp;{{ $categories->count() }} kategori</span>
                                    <span class="hero-stat-item"><i class="bi bi-whatsapp"></i>&nbsp;Via WhatsApp</span>
                                </div>
                                <div class="hero-cta-wrap">
                                    <a href="#produk" class="hero-btn-primary"><i class="bi bi-grid-fill"></i>&nbsp;Jelajahi Produk</a>
                                    <a href="https://chat.whatsapp.com/F2SA2usTXXSFXYgJyvcX3k" target="_blank" rel="noopener noreferrer"
                                       class="hero-btn-outline" style="background:rgba(37,211,102,.15);border-color:rgba(37,211,102,.5);"><i class="bi bi-person-plus-fill"></i>&nbsp;Join Grup</a>
                                </div>
                            </div>
                            <div class="col-lg-5 offset-lg-1 d-none d-lg-flex align-items-center">
                                <div class="hero-product-card-wrap w-100">
                                    <div class="hero-product-card">
                                        @if($hlIsVideo && !empty($hl->media_urls[0]))
                                            <video autoplay muted loop playsinline class="hero-product-img" style="width:100%;height:auto;display:block;">
                                                <source src="{{ $hl->media_urls[0] }}" type="video/mp4">
                                            </video>
                                        @elseif(!empty($hl->media_urls[0]))
                                            <img src="{{ $hl->media_urls[0] }}" alt="{{ $hl->title }}" class="hero-product-img">
                                        @endif
                                        <div class="hero-product-body">
                                            @if($hl->category)
                                                <div class="hero-product-cat">{{ $hl->category->name }}</div>
                                            @endif
                                            <div class="hero-product-title">{{ $hl->title }}</div>
                                            @if($hl->price_label)
                                                <div class="hero-product-price">{{ $hl->price_label }}</div>
                                            @elseif($hl->price && $hl->price > 0)
                                                <div class="hero-product-price">Rp {{ number_format($hl->price, 0, ',', '.') }}</div>
                                            @else
                                                <div class="hero-product-price">Harga nego</div>
                                            @endif
                                            <div class="hero-product-seller">
                                                <i class="bi bi-person-circle"></i>
                                                {{ $hl->contact?->name ?: ($hl->contact_name ?: 'Penjual') }}
                                            </div>
                                            @if($hlLink)
                                                <a href="{{ $hlLink }}" target="_blank" rel="noopener" class="hero-btn-primary" style="font-size:.8rem;padding:.52rem 1.15rem;">
                                                    <i class="bi bi-whatsapp"></i>&nbsp;Hubungi Penjual
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @if($heroListings->count() > 1)
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
        </button>
        <div class="carousel-indicators">
            @foreach($heroListings as $hl)
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="{{ $loop->index }}"
                    class="{{ $loop->first ? 'active' : '' }}"
                    {{ $loop->first ? 'aria-current="true"' : '' }}></button>
            @endforeach
        </div>
        @endif
    </div>
    @else
    {{-- Fallback: no product images yet --}}
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,#042f24 0%,#064e3b 55%,#059669 100%);"></div>
    <div class="hero-slide-content">
        <div class="container">
            <div class="col-lg-6 col-12">
                <div class="hero-eyebrow"><i class="bi bi-robot"></i>&nbsp;AI &middot; WhatsApp Marketplace</div>
                <h1>Semua Produk<br><em>Komunitas Jamaah</em><br>dalam Satu Tempat</h1>
                <p class="hero-lead">Produk dari grup WhatsApp jamaah dideteksi &amp; dikurasi otomatis oleh AI &mdash; mudah, cepat, dan terpercaya.</p>
                <div class="hero-stats">
                    <span class="hero-stat-item"><i class="bi bi-tag-fill"></i>&nbsp;{{ $totalActive }} produk aktif</span>
                    <span class="hero-stat-item"><i class="bi bi-whatsapp"></i>&nbsp;Via WhatsApp</span>
                </div>
                <div class="hero-cta-wrap">
                    <a href="#produk" class="hero-btn-primary"><i class="bi bi-grid-fill"></i>&nbsp;Jelajahi Produk</a>
                    <a href="https://chat.whatsapp.com/F2SA2usTXXSFXYgJyvcX3k" target="_blank" rel="noopener noreferrer"
                       class="hero-btn-outline" style="background:rgba(37,211,102,.15);border-color:rgba(37,211,102,.5);"><i class="bi bi-person-plus-fill"></i>&nbsp;Join Grup</a>
                </div>
            </div>
        </div>
    </div>
    @endif
</section>

<div class="container" id="produk">
    {{-- Search card --}}
    <div class="search-card mb-4">
        <form method="GET" action="{{ url('/') }}">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md">
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-color:#d1d5db;border-right:none;">
                            <i class="bi bi-search" style="color:#9ca3af;"></i>
                        </span>
                        <input type="text" name="search" class="form-control" placeholder="Cari nama produk, deskripsi..."
                            value="{{ request('search') }}"
                            style="border-color:#d1d5db;border-left:none;font-size:.9rem;color:#111827;">
                    </div>
                </div>
                <div class="col-12 col-md-auto d-flex gap-2">
                    <button type="submit" class="btn-search flex-fill">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                    <button type="button" class="btn-reset" data-bs-toggle="collapse" data-bs-target="#filterAdvanced"
                            title="Filter harga & lokasi"
                            style="{{ request()->hasAny(['min_price','max_price','location']) ? 'color:#059669;border-color:#059669;' : '' }}">
                        <i class="bi bi-sliders"></i>
                    </button>
                    @if(request()->hasAny(['search','category_id','min_price','max_price','location']))
                        <a href="{{ url('/') }}" class="btn-reset" title="Reset semua filter">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Advanced filters (collapsible) --}}
            <div class="collapse {{ request()->hasAny(['min_price','max_price','location']) ? 'show' : '' }}" id="filterAdvanced">
                <div class="row g-2 mt-2">
                    <div class="col-6 col-md-3">
                        <label class="form-label" style="font-size:.78rem;color:#6b7280;font-weight:600;margin-bottom:.25rem;">Harga Min (Rp)</label>
                        <input type="number" name="min_price" class="form-control form-control-sm"
                               placeholder="contoh: 50000"
                               value="{{ request('min_price') }}"
                               min="0" step="1000"
                               style="border-color:#d1d5db;font-size:.88rem;">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" style="font-size:.78rem;color:#6b7280;font-weight:600;margin-bottom:.25rem;">Harga Max (Rp)</label>
                        <input type="number" name="max_price" class="form-control form-control-sm"
                               placeholder="contoh: 500000"
                               value="{{ request('max_price') }}"
                               min="0" step="1000"
                               style="border-color:#d1d5db;font-size:.88rem;">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" style="font-size:.78rem;color:#6b7280;font-weight:600;margin-bottom:.25rem;">Lokasi Penjual</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white" style="border-color:#d1d5db;">
                                <i class="bi bi-geo-alt" style="color:#9ca3af;"></i>
                            </span>
                            <input type="text" name="location" class="form-control"
                                   placeholder="contoh: Bekasi, Jakarta Selatan..."
                                   value="{{ request('location') }}"
                                   style="border-color:#d1d5db;font-size:.88rem;">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Category pills --}}
            @php
                $qBase = array_filter(request()->only(['search','min_price','max_price','location']));
            @endphp
            <div class="cat-pills-wrap mt-3">
                <a href="{{ url('/') }}{{ $qBase ? '?'.http_build_query($qBase) : '' }}"
                   class="cat-pill {{ !request('category_id') ? 'active' : '' }}">
                    <i class="bi bi-grid me-1"></i>Semua
                </a>
                @foreach($categories as $cat)
                    <a href="{{ url('/') }}?{{ http_build_query(array_merge($qBase, ['category_id' => $cat->id])) }}"
                       class="cat-pill {{ request('category_id') == $cat->id ? 'active' : '' }}">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
        </form>
    </div>

    {{-- Feature strip --}}
    <div class="feature-strip">
        <div class="row g-2">
            <div class="col-md-3 col-6">
                <div class="feature-item"><i class="bi bi-shield-check-fill"></i>Penjual Terverifikasi</div>
            </div>
            <div class="col-md-3 col-6">
                <div class="feature-item"><i class="bi bi-whatsapp"></i>Hubungi via WhatsApp</div>
            </div>
            <div class="col-md-3 col-6">
                <div class="feature-item"><i class="bi bi-robot"></i>Dikurasi AI Otomatis</div>
            </div>
            <div class="col-md-3 col-6">
                <div class="feature-item"><i class="bi bi-people-fill"></i>Komunitas Jamaah</div>
            </div>
        </div>
    </div>

    {{-- Top 5 Categories --}}
    @if($topCategories->isNotEmpty())
    <div class="mb-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span style="font-weight:700;font-size:.95rem;color:#111827;"><i class="bi bi-bar-chart-fill me-1" style="color:#059669;"></i>Kategori Terpopuler</span>
            <span style="font-size:.75rem;color:#9ca3af;">berdasarkan jumlah produk aktif</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @foreach($topCategories as $i => $tc)
            @php
                $rank = $i + 1;
                $palette = ['#dcfce7','#d1fae5','#a7f3d0','#6ee7b7','#34d399'];
                $textPalette = ['#065f46','#065f46','#065f46','#065f46','#064e3b'];
                $bg = $palette[$i] ?? '#f3f4f6';
                $fg = $textPalette[$i] ?? '#374151';
            @endphp
            <a href="{{ url('/') }}?category_id={{ $tc->id }}"
               style="display:flex;align-items:center;gap:.55rem;background:{{ $bg }};border:none;border-radius:12px;padding:.5rem .9rem;text-decoration:none;color:{{ $fg }};font-weight:600;font-size:.82rem;transition:transform .12s,box-shadow .12s;box-shadow:0 1px 3px rgba(0,0,0,.07);"
               onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 10px rgba(0,0,0,.12)'"
               onmouseout="this.style.transform='';this.style.boxShadow='0 1px 3px rgba(0,0,0,.07)'">
                <span style="font-size:.95rem;font-weight:800;opacity:.6;">#{{ $rank }}</span>
                {{ $tc->name }}
                <span style="background:rgba(0,0,0,.08);border-radius:20px;padding:1px 7px;font-size:.73rem;font-weight:700;">{{ $tc->listings_count }}</span>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- WhatsApp Group Join Banner --}}
    <div class="wa-join-banner mb-4">
        <div class="row align-items-center g-4">
            <div class="col-auto">
                <div class="wa-join-qr-wrap">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=https%3A%2F%2Fchat.whatsapp.com%2FF2SA2usTXXSFXYgJyvcX3k&bgcolor=ffffff&color=064e3b&margin=5"
                         alt="QR Code Join Grup WhatsApp Jamaah"
                         width="110" height="110" loading="lazy"
                         style="display:block;border-radius:6px;">
                </div>
            </div>
            <div class="col">
                <div class="wa-join-label"><i class="bi bi-whatsapp"></i>&nbsp;Komunitas WhatsApp Jamaah</div>
                <div class="wa-join-title">Gabung Grup WhatsApp Kami Sekarang!</div>
                <div class="wa-join-desc">Scan QR code atau klik tombol di bawah untuk bergabung. Jual produkmu, temukan pembeli, dan dapatkan penawaran terbaik dari komunitas jamaah.</div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <a href="https://chat.whatsapp.com/F2SA2usTXXSFXYgJyvcX3k"
                       target="_blank" rel="noopener noreferrer" class="wa-join-btn">
                        <i class="bi bi-whatsapp" style="font-size:1.1rem;"></i>&nbsp;Join Grup WhatsApp
                    </a>
                </div>
                <div class="wa-join-link"><i class="bi bi-link-45deg"></i>&nbsp;https://chat.whatsapp.com/F2SA2usTXXSFXYgJyvcX3k</div>
            </div>
        </div>
    </div>

    {{-- Moderation notice --}}
    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:.75rem 1rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.6rem;">
        <span style="font-size:1.1rem;flex-shrink:0;">⏳</span>
        <div style="font-size:.82rem;color:#92400e;line-height:1.55;">
            <strong>Produk belum muncul?</strong> Jika kamu baru saja posting iklan di grup WhatsApp, iklanmu sedang dalam proses moderasi AI dan akan tampil otomatis dalam beberapa menit.
            Pastikan iklan sudah menggunakan format lengkap (nama + harga + deskripsi + foto).
        </div>
    </div>

    {{-- Product grid — with media first --}}
    @if($listings->isEmpty() && $textListings->isEmpty())
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-bag-x"></i></div>
            <h5>Produk tidak ditemukan</h5>
            <p>Coba kata kunci lain atau pilih kategori berbeda</p>
            <a href="{{ url('/') }}" class="btn-search" style="display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;margin-top:.5rem;border-radius:10px;">
                <i class="bi bi-arrow-left"></i>Lihat Semua Produk
            </a>
        </div>
    @else

        {{-- ── Section: Produk dengan Foto/Video ───────────────────── --}}
        @if($listings->isNotEmpty())
        <div class="section-divider">
            <div class="section-divider-title">
                <i class="bi bi-image-fill" style="color:#059669;"></i>
                Produk dengan Foto/Video
                <span style="font-size:.78rem;color:#9ca3af;font-weight:500;">{{ $listings->total() }} produk</span>
            </div>
            <div class="section-divider-line"></div>
        </div>

        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-3 g-3 mb-2" id="productGrid">
            @include('landing._cards', ['listings' => $listings->items()])
        </div>

        @if($listings->hasMorePages())
        <div id="loadMoreMediaWrap" style="text-align:center;margin-top:1.2rem;margin-bottom:1.5rem;">
            <button id="loadMoreMediaBtn" onclick="loadMoreMedia()"
                    style="background:#fff;border:1.5px solid #d1d5db;border-radius:10px;padding:.58rem 2.2rem;font-size:.85rem;font-weight:700;color:#374151;cursor:pointer;transition:all .18s;"
                    onmouseover="this.style.borderColor='#059669';this.style.color='#059669'"
                    onmouseout="if(!this.disabled){this.style.borderColor='#d1d5db';this.style.color='#374151'}">
                <i class="bi bi-arrow-down-circle me-1"></i>Muat Lebih Banyak Produk
            </button>
        </div>
        @endif
        @endif

        {{-- ── Section: Iklan Baris (tanpa foto) ────────────────── --}}
        @if($textListings->isNotEmpty())
        <div class="section-divider" style="margin-top:3rem;">
            <div class="section-divider-title">
                <i class="bi bi-list-ul" style="color:#059669;"></i>
                Iklan Baris
                <span style="font-size:.78rem;color:#9ca3af;font-weight:500;">{{ $textListings->total() }} iklan &middot; tanpa foto</span>
            </div>
            <div class="section-divider-line"></div>
        </div>
        <p style="font-size:.8rem;color:#6b7280;margin-bottom:.9rem;margin-top:-.4rem;">Iklan teks tanpa foto &mdash; klik untuk detail, atau hubungi penjual langsung via WhatsApp.</p>

        <div id="iklanBarisList" class="iklan-baris-list mb-3">
            @include('landing._iklan_baris', ['listings' => $textListings->items()])
        </div>

        @if($textListings->hasMorePages())
        <div id="loadMoreTextWrap" style="text-align:center;margin-bottom:2rem;">
            <button id="loadMoreTextBtn" onclick="loadMoreText()"
                    style="background:#fff;border:1.5px solid #d1d5db;border-radius:10px;padding:.58rem 2.2rem;font-size:.85rem;font-weight:700;color:#374151;cursor:pointer;transition:all .18s;"
                    onmouseover="this.style.borderColor='#059669';this.style.color='#059669'"
                    onmouseout="if(!this.disabled){this.style.borderColor='#d1d5db';this.style.color='#374151'}">
                <i class="bi bi-arrow-down-circle me-1"></i>Muat Lebih Banyak Iklan
            </button>
        </div>
        @endif
        @endif

    @endif
</div>

{{-- Footer --}}
<footer class="site-footer">
    <div class="container">
        <div class="mb-1" style="color:rgba(255,255,255,.85);">
            <strong style="color:#fff;">MarketplaceJamaah AI</strong> — Platform jual beli otomatis dari grup WhatsApp jamaah
        </div>
        <div style="color:rgba(255,255,255,.5);">
            Powered by AI &middot; <a href="{{ url('/marketing-tools') }}">Fitur</a> &middot; <a href="{{ url('/panduan') }}">Panduan</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function() {
    // ── Load More Products (with media) ──────────────────────────────
    var _page    = 1;
    var _loading = false;
    var _search   = @json(request('search', ''));
    var _catId    = @json(request('category_id', ''));
    var _minPrice = @json(request('min_price', ''));
    var _maxPrice = @json(request('max_price', ''));
    var _location = @json(request('location', ''));
    var _grid    = document.getElementById('productGrid');

    function _buildParams(page, type) {
        var p = new URLSearchParams({ page: page, type: type });
        if (_search)   p.append('search',      _search);
        if (_catId)    p.append('category_id', _catId);
        if (_minPrice) p.append('min_price',   _minPrice);
        if (_maxPrice) p.append('max_price',   _maxPrice);
        if (_location) p.append('location',    _location);
        return p;
    }

    window.loadMoreMedia = function() {
        if (_loading) return;
        var btn = document.getElementById('loadMoreMediaBtn');
        _loading = true;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Memuat...'; }
        _page++;

        var p = _buildParams(_page, 'media');

        fetch('/produk/lagi?' + p.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function(d) {
                if (_grid) _grid.insertAdjacentHTML('beforeend', d.html);
                if (!d.hasMore) {
                    var wrap = document.getElementById('loadMoreMediaWrap');
                    if (wrap) wrap.style.display = 'none';
                } else if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i>Muat Lebih Banyak Produk';
                }
                _initVideoAutoplay();
            })
            .catch(function(e) {
                console.error('load-more-media:', e); _page--;
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i>Muat Lebih Banyak Produk'; }
            })
            .finally(function() { _loading = false; });
    };

    // ── Text (Iklan Baris) Load More ────────────────────────────────────────
    var _textPage    = 1;
    var _textLoading = false;
    window.loadMoreText = function () {
        if (_textLoading) return;
        var btn = document.getElementById('loadMoreTextBtn');
        _textLoading = true;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Memuat...'; }
        _textPage++;
        var p2 = _buildParams(_textPage, 'text');
        fetch('/produk/lagi?' + p2.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function(d) {
                var list = document.getElementById('iklanBarisList');
                if (list) list.insertAdjacentHTML('beforeend', d.html);
                if (!d.hasMore) {
                    var wrap = document.getElementById('loadMoreTextWrap');
                    if (wrap) wrap.style.display = 'none';
                } else if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i>Muat Lebih Banyak Iklan';
                }
            })
            .catch(function(e) {
                console.error('load-more-text:', e); _textPage--;
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-arrow-down-circle me-1"></i>Muat Lebih Banyak Iklan'; }
            })
            .finally(function() { _textLoading = false; });
    };

    // ── Video autoplay when scrolled into view ────────────────────────────
    function _initVideoAutoplay() {
        if (!('IntersectionObserver' in window)) return;
        document.querySelectorAll('.card-autoplay-video:not([data-observed])').forEach(function(vid) {
            vid.setAttribute('data-observed', '1');
            // Lazy-load src on first observation
            var autoObs = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        if (!vid.src && vid.dataset.src) {
                            vid.src = vid.dataset.src;
                        }
                        vid.play().catch(function(){});
                    } else {
                        vid.pause();
                    }
                });
            }, { threshold: 0.25 });
            autoObs.observe(vid);
        });
    }
    _initVideoAutoplay();

})();
</script>
</body>
</html>
