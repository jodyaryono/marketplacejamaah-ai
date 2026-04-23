<!DOCTYPE html>
@php
    $__loc = \App\Support\SiteLocale::get();
    $__t = fn($id, $en) => $__loc === 'en' ? $en : $id;
    $__langUrl = function($target) {
        $qs = array_merge(request()->query(), ['lang' => $target]);
        return url()->current() . '?' . http_build_query($qs);
    };
@endphp
<html lang="{{ $__loc }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <title>@yield('title', $__t('Marketplace Jamaah', 'Marketplace Jamaah'))</title>
    <meta name="description" content="@yield('description', $__t('Jual beli produk halal komunitas jamaah Indonesia.', 'Halal community buying and selling — Indonesia jamaah.'))">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --em:#059669; --em-dark:#047857; --em-light:#a7f3d0; --em-xlight:#ecfdf5; --hair:#e5e7eb; }
        * { box-sizing: border-box; }
        body { background:#f0fdf8; color:#111827; font-family:'Plus Jakarta Sans','Segoe UI',sans-serif; }
        .pub-nav-wrap { position:sticky; top:0; z-index:100; padding:.55rem 0; }
        .pub-nav {
            background:rgba(255,255,255,.78); backdrop-filter:saturate(1.6) blur(18px);
            -webkit-backdrop-filter:saturate(1.6) blur(18px);
            border:1px solid rgba(15,23,42,.06); border-radius:18px; padding:.55rem .9rem;
            box-shadow:0 10px 28px -10px rgba(5,150,105,.18), 0 2px 6px rgba(15,23,42,.04);
            display:flex; align-items:center; justify-content:space-between; gap:.75rem;
        }
        .brand-logo { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#059669,#34d399); display:flex; align-items:center; justify-content:center; flex-shrink:0;}
        .brand-logo i { color:#fff; font-size:1.1rem; }
        .brand-name { font-size:1rem; font-weight:800; color:#111827; line-height:1.1;}
        .brand-name span { color:var(--em); }
        .brand-sub { font-size:.6rem; color:#6b7280; font-weight:600; }
        .btn-pub-primary { background:linear-gradient(135deg,#059669,#10b981); color:#fff; border:none; border-radius:10px; font-size:.82rem; font-weight:700; padding:.45rem 1.2rem; text-decoration:none; display:inline-flex; align-items:center; gap:.35rem; transition:all .2s; }
        .btn-pub-primary:hover { color:#fff; transform:translateY(-1px); }
        .btn-pub-outline { background:#fff; color:#374151; border:1px solid var(--hair); border-radius:10px; font-size:.82rem; font-weight:700; padding:.4rem 1rem; text-decoration:none; display:inline-flex; align-items:center; gap:.35rem; transition:all .15s; }
        .btn-pub-outline:hover { border-color:var(--em); color:var(--em); }
        .pub-lang-pill { display:inline-flex; padding:3px; background:#f1f5f9; border-radius:999px; border:1px solid var(--hair); }
        .pub-lang-pill a { padding:.25rem .65rem; border-radius:999px; font-size:.72rem; font-weight:800; color:#475569; text-decoration:none; letter-spacing:.04em;}
        .pub-lang-pill a.on { background:#0b1220; color:#fff; box-shadow:0 4px 12px rgba(11,18,32,.22); }
        .pub-footer { background:#022c22; color:#94a3b8; padding:2rem 0; margin-top:4rem; font-size:.82rem; }
        .pub-footer a { color:#6ee7b7; text-decoration:none; font-weight:600; }
    </style>
    @yield('meta')
    @yield('styles')
</head>
<body>
<div class="pub-nav-wrap">
    <div class="container">
        <nav class="pub-nav">
            <a href="{{ route('landing') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <div class="brand-logo"><i class="bi bi-shop"></i></div>
                <div>
                    <div class="brand-name">Marketplace<span>Jamaah</span></div>
                    <div class="brand-sub">{{ $__t('Jual Beli Komunitas', 'Community Marketplace') }}</div>
                </div>
            </a>
            <div class="d-flex gap-2 align-items-center">
                <div class="pub-lang-pill" role="group" aria-label="Language">
                    <a href="{{ $__langUrl('id') }}" class="{{ $__loc==='id'?'on':'' }}">ID</a>
                    <a href="{{ $__langUrl('en') }}" class="{{ $__loc==='en'?'on':'' }}">EN</a>
                </div>
                <a href="{{ route('landing') }}" class="btn-pub-outline d-none d-sm-flex"><i class="bi bi-grid"></i> {{ $__t('Semua Produk','All Products') }}</a>
            </div>
        </nav>
    </div>
</div>

<main>@yield('content')</main>

<footer class="pub-footer">
    <div class="container">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div>© {{ date('Y') }} MarketplaceJamaah · {{ $__t('Jual beli komunitas halal Indonesia','Halal Indonesia community marketplace') }}</div>
            <div class="d-flex gap-3">
                <a href="{{ route('landing') }}">{{ $__t('Beranda','Home') }}</a>
                <a href="{{ url('/fitur') }}">{{ $__t('Fitur','Features') }}</a>
                <a href="{{ route('panduan') }}">{{ $__t('Panduan','Guide') }}</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
