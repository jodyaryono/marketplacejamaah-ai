<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Marketplace Jamaah')</title>
    <meta name="description" content="@yield('description', 'Jual beli produk halal komunitas jamaah Indonesia.')">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --em:#059669; --em-dark:#047857; --em-light:#a7f3d0; --em-xlight:#ecfdf5; }
        * { box-sizing: border-box; }
        body { background:#f0fdf8; color:#111827; font-family:'Plus Jakarta Sans','Segoe UI',sans-serif; }
        .pub-nav {
            background:rgba(255,255,255,.92); backdrop-filter:blur(12px);
            border-bottom:1.5px solid rgba(167,243,208,.5);
            padding:.7rem 0; position:sticky; top:0; z-index:100;
            box-shadow:0 2px 16px rgba(5,150,105,.1);
        }
        .brand-logo { width:38px; height:38px; border-radius:10px; background:linear-gradient(135deg,#059669,#34d399); display:flex; align-items:center; justify-content:center; }
        .brand-logo i { color:#fff; font-size:1.1rem; }
        .brand-name { font-size:1rem; font-weight:800; color:#111827; }
        .brand-name span { color:var(--em); }
        .btn-pub-primary { background:linear-gradient(135deg,#059669,#10b981); color:#fff; border:none; border-radius:10px; font-size:.82rem; font-weight:700; padding:.45rem 1.2rem; text-decoration:none; display:inline-flex; align-items:center; gap:.35rem; transition:all .2s; }
        .btn-pub-primary:hover { color:#fff; transform:translateY(-1px); }
        .btn-pub-outline { background:#fff; color:#374151; border:1.5px solid #d1d5db; border-radius:10px; font-size:.82rem; font-weight:600; padding:.4rem 1rem; text-decoration:none; display:inline-flex; align-items:center; gap:.35rem; transition:all .15s; }
        .btn-pub-outline:hover { border-color:var(--em); color:var(--em); }
        .pub-footer { background:#022c22; color:#94a3b8; padding:2rem 0; margin-top:4rem; font-size:.82rem; }
        .pub-footer a { color:#6ee7b7; text-decoration:none; }
    </style>
    @yield('styles')
</head>
<body>
<nav class="pub-nav">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <a href="{{ route('landing') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <div class="brand-logo"><i class="bi bi-shop"></i></div>
                <div>
                    <div class="brand-name">Marketplace<span>Jamaah</span></div>
                    <div style="font-size:.6rem;color:#6b7280;font-weight:500;">Jual Beli Komunitas</div>
                </div>
            </a>
            <div class="d-flex gap-2 align-items-center">
                <a href="{{ route('landing') }}" class="btn-pub-outline d-none d-sm-flex"><i class="bi bi-grid"></i> Semua Produk</a>
            </div>
        </div>
    </div>
</nav>

<main>@yield('content')</main>

<footer class="pub-footer">
    <div class="container">
        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-3">
            <div>© {{ date('Y') }} MarketplaceJamaah · Jual beli komunitas halal Indonesia</div>
            <div class="d-flex gap-3">
                <a href="{{ route('landing') }}">Beranda</a>
                <a href="{{ route('marketing-tools') }}">Fitur</a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
