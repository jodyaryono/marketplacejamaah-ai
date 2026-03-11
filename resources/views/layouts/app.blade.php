<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — MarketplaceJamaah AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 240px;
            --sidebar-bg: #022c22;
            --sidebar-border: rgba(255,255,255,.07);
            --card-bg: #ffffff;
            --body-bg: #f0fdf8;
            --accent: #059669;
            --accent-hover: #047857;
        }
        * { box-sizing: border-box; }
        body { background: var(--body-bg); color: #111827; font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; }

        /* ── Sidebar ── */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            border-right: none;
            position: fixed; top: 0; left: 0; z-index: 1000;
            transition: all .25s;
            box-shadow: 4px 0 24px rgba(0,0,0,.18);
        }
        #sidebar .sidebar-brand {
            padding: 1.25rem 1rem;
            border-bottom: 1px solid var(--sidebar-border);
        }
        #sidebar .sidebar-brand span { font-size: 1rem; font-weight: 700; color: #f1f5f9; }
        #sidebar .sidebar-brand small { color: #6ee7b7; font-size: .7rem; }
        #sidebar .nav-link {
            color: #94a3b8; padding: .6rem 1.1rem;
            border-radius: 8px; margin: 1px 8px;
            display: flex; align-items: center; gap: .6rem;
            font-size: .85rem; font-weight: 500; transition: all .15s;
            text-decoration: none;
        }
        #sidebar .nav-link:hover { background: rgba(5,150,105,.18); color: #6ee7b7; }
        #sidebar .nav-link.active {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; font-weight: 600;
            box-shadow: 0 4px 12px rgba(5,150,105,.45);
        }
        #sidebar .nav-link i { font-size: 1rem; width: 18px; text-align: center; }
        #sidebar .nav-section { padding: .5rem 1.1rem .25rem; font-size: .65rem; text-transform: uppercase; letter-spacing: .1em; color: #475569; margin-top: .5rem; }

        /* ── Main ── */
        #main-content { margin-left: var(--sidebar-width); min-height: 100vh; }

        /* ── Topbar ── */
        .topbar {
            background: rgba(255,255,255,.88);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid #d1fae5;
            padding: .75rem 1.5rem;
            position: sticky; top: 0; z-index: 900;
            box-shadow: 0 1px 8px rgba(5,150,105,.08);
        }

        /* ── Cards ── */
        .card {
            background: #ffffff !important;
            border: 1px solid #d1fae5 !important;
            border-radius: 16px !important;
            box-shadow: 0 2px 12px rgba(5,150,105,.08) !important;
        }
        .card-header {
            background: #f0fdf8 !important;
            border-bottom: 1px solid #d1fae5 !important;
            border-radius: 16px 16px 0 0 !important;
        }

        /* ── Stat cards ── */
        .stat-card {
            border-radius: 16px; padding: 1.4rem;
            border: none; transition: transform .2s, box-shadow .2s;
            position: relative; overflow: hidden;
        }
        .stat-card::after {
            content: ''; position: absolute; top: -30px; right: -30px;
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(255,255,255,.12); pointer-events: none;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-card.card-blue { background: linear-gradient(135deg, #059669, #0d9488); box-shadow: 0 8px 24px rgba(5,150,105,.45); }
        .stat-card.card-amber { background: linear-gradient(135deg, #d97706, #f59e0b); box-shadow: 0 8px 24px rgba(217,119,6,.45); }
        .stat-card.card-emerald { background: linear-gradient(135deg, #059669, #10b981); box-shadow: 0 8px 24px rgba(5,150,105,.45); }
        .stat-card.card-rose { background: linear-gradient(135deg, #db2777, #e11d48); box-shadow: 0 8px 24px rgba(219,39,119,.45); }
        .stat-card .stat-icon {
            width: 46px; height: 46px; border-radius: 12px;
            background: rgba(255,255,255,.2);
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .stat-card .stat-icon i { color: #fff; }
        .stat-card .stat-badge {
            background: rgba(255,255,255,.2); color: #fff;
            font-size: .68rem; font-weight: 700; border-radius: 20px;
            padding: .2rem .65rem; border: 1px solid rgba(255,255,255,.3);
        }
        .stat-card .stat-value { font-size: 2rem; font-weight: 800; color: #fff; line-height: 1; }
        .stat-card .stat-label { font-size: .8rem; color: rgba(255,255,255,.8); margin-top: .25rem; }
        .stat-card .stat-trend { font-size: .75rem; color: rgba(255,255,255,.9); }
        .stat-card .stat-trend i { color: #fff; }

        /* ── Tables ── */
        .table { color: #111827; }
        .table th { color: #374151; font-size: .73rem; text-transform: uppercase; letter-spacing: .05em; border-color: #d1fae5 !important; font-weight: 700; background: #f0fdf8; padding: .75rem 1rem; }
        .table td { border-color: #ecfdf5 !important; vertical-align: middle; padding: .75rem 1rem; color: #111827; }
        .table tbody tr:hover { background: #ecfdf5; }

        /* ── Badges ── */
        .badge { font-size: .7rem; font-weight: 600; }

        /* ── Forms ── */
        .form-control, .form-select {
            background: #ffffff !important; border-color: #d1d5db !important;
            color: #111827 !important; border-radius: 8px;
        }
        .form-control:focus, .form-select:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 3px rgba(5,150,105,.15) !important; }
        .form-control::placeholder { color: #9ca3af; }
        .btn-primary { background: linear-gradient(135deg, #059669, #10b981); border: none; color: #fff; box-shadow: 0 4px 12px rgba(5,150,105,.4); }
        .btn-primary:hover { background: linear-gradient(135deg, #047857, #059669); border: none; }

        /* ── Page header ── */
        .page-header { padding: 1.5rem; border-bottom: 1px solid #d1fae5; margin-bottom: 0; background: rgba(255,255,255,.7); backdrop-filter: blur(8px); }
        .page-header h1 { font-size: 1.375rem; font-weight: 700; color: #111827; margin: 0; }
        .page-header p { color: #6b7280 !important; }
        .page-body { padding: 1.5rem; }

        /* ── Mobile ── */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); }
            #main-content { margin-left: 0; }
        }

        /* ── Activity feed ── */
        .activity-item { padding: .75rem 0; }
        .activity-item:last-child { border-bottom: none !important; }

        /* ── Realtime dot ── */
        .realtime-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; animation: pulse-dot 2s infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: #f0fdf8; }
        ::-webkit-scrollbar-thumb { background: #6ee7b7; border-radius: 3px; }

        .modal-content { background: #ffffff !important; border: 1px solid #d1fae5 !important; border-radius: 16px !important; }
        .modal-header, .modal-footer { border-color: #d1fae5 !important; }
        .modal-title { color: #111827 !important; }
        .dropdown-menu { background: #ffffff; border-color: #d1fae5; box-shadow: 0 8px 24px rgba(5,150,105,.15); border-radius: 12px; }
        .dropdown-item { color: #374151; }
        .dropdown-item:hover { background: #ecfdf5; color: #059669; }
        .pagination .page-link { background: #ffffff; border-color: #d1fae5; color: #059669; border-radius: 8px !important; }
        .pagination .page-item.active .page-link { background: linear-gradient(135deg, #059669, #10b981); border-color: transparent; color: #fff; box-shadow: 0 2px 8px rgba(5,150,105,.4); }
    </style>
    @stack('styles')
</head>
<body>

<!-- Sidebar -->
<nav id="sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-2">
        <div style="width:36px;height:36px;background:linear-gradient(135deg,#059669,#10b981);border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(5,150,105,.5);">
            <i class="bi bi-robot text-white" style="font-size:1.1rem;"></i>
        </div>
        <div>
            <span>MarketplaceJamaah</span><br>
            <small>AI Platform</small>
        </div>
    </div>

    <div class="mt-2">
        <div class="nav-section">Main</div>
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard*') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="{{ route('messages.index') }}" class="nav-link {{ request()->routeIs('messages*') ? 'active' : '' }}">
            <i class="bi bi-chat-dots"></i> Pesan Masuk
            @php $unread = \App\Models\Message::where('is_processed', false)->count(); @endphp
            @if($unread > 0)
                <span class="badge bg-danger ms-auto">{{ $unread > 99 ? '99+' : $unread }}</span>
            @endif
        </a>
        <a href="{{ route('listings.index') }}" class="nav-link {{ request()->routeIs('listings*') ? 'active' : '' }}">
            <i class="bi bi-shop"></i> Marketplace
        </a>

        <div class="nav-section">Kelola</div>
        <a href="{{ route('groups.index') }}" class="nav-link {{ request()->routeIs('groups*') ? 'active' : '' }}">
            <i class="bi bi-people"></i> Grup WhatsApp
        </a>
        <a href="{{ route('contacts.index') }}" class="nav-link {{ request()->routeIs('contacts*') ? 'active' : '' }}">
            <i class="bi bi-person-lines-fill"></i> Kontak
        </a>
        <a href="{{ route('categories.index') }}" class="nav-link {{ request()->routeIs('categories*') ? 'active' : '' }}">
            <i class="bi bi-tags"></i> Kategori
        </a>
        <a href="{{ route('system-messages.index') }}" class="nav-link {{ request()->routeIs('system-messages*') ? 'active' : '' }}">
            <i class="bi bi-chat-square-text"></i> Pesan Sistem
        </a>

        <div class="nav-section">Sistem</div>
        <a href="{{ route('agents.index') }}" class="nav-link {{ request()->routeIs('agents.index') ? 'active' : '' }}">
            <i class="bi bi-cpu"></i> AI Monitor
        </a>
        <a href="{{ route('agents.docs') }}" class="nav-link {{ request()->routeIs('agents.docs') ? 'active' : '' }}">
            <i class="bi bi-book"></i> AI Docs
        </a>
        <a href="{{ route('ai-health.index') }}" class="nav-link {{ request()->routeIs('ai-health*') ? 'active' : '' }}">
            <i class="bi bi-heart-pulse"></i> AI Health & Token
        </a>
        @can('view users')
        <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users*') ? 'active' : '' }}">
            <i class="bi bi-shield-person"></i> Users
        </a>
        @endcan
        <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings*') ? 'active' : '' }}">
            <i class="bi bi-gear"></i> Pengaturan
        </a>
    </div>

    <!-- User info at bottom -->
    <div style="position:absolute;bottom:0;left:0;right:0;padding:1rem;border-top:1px solid rgba(255,255,255,.07);background:rgba(0,0,0,.2);">
        <div class="d-flex align-items-center gap-2">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#059669,#10b981);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;color:#fff;box-shadow:0 2px 8px rgba(5,150,105,.5);">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div style="flex:1;overflow:hidden;">
                <div style="font-size:.8rem;font-weight:600;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ auth()->user()->name }}</div>
                <div style="font-size:.7rem;color:#94a3b8;">{{ auth()->user()->getRoleNames()->first() }}</div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm" style="color:#9ca3af;padding:.25rem;background:none;border:none;" title="Logout">
                    <i class="bi bi-box-arrow-right"></i>
                </button>
            </form>
        </div>
    </div>
</nav>

<!-- Main content -->
<div id="main-content">
    <!-- Topbar -->
    <div class="topbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-sm d-md-none" onclick="document.getElementById('sidebar').classList.toggle('show')" style="color:#94a3b8;background:none;border:none;font-size:1.2rem;">
                <i class="bi bi-list"></i>
            </button>
            <div>
                <span style="color:#374151;font-size:.875rem;font-weight:500;">@yield('breadcrumb', 'Dashboard')</span>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="d-flex align-items-center gap-1" style="font-size:.75rem;color:#374151;font-weight:500;">
                <span class="realtime-dot"></span> Live
            </span>
            <div style="font-size:.8rem;color:#6b7280;">{{ now()->format('d M Y, H:i') }}</div>
        </div>
    </div>

    <!-- Alerts -->
    <div class="px-3 pt-2">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show py-2" style="background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#86efac;">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error') || $errors->any())
            <div class="alert alert-danger alert-dismissible fade show py-2" style="background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#fca5a5;">
                <i class="bi bi-exclamation-circle me-2"></i>
                {{ session('error') ?? $errors->first() }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        @endif
    </div>

    @yield('content')
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
@stack('scripts')
</body>
</html>
