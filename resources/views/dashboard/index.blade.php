@extends('layouts.app')
@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@section('content')
<div class="page-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <h1 style="font-size:1.4rem;font-weight:800;color:#111827;margin:0;">
            <i class="bi bi-speedometer2 me-2" style="color:#059669;"></i>Dashboard
        </h1>
        <p class="mb-0 mt-1" style="color:#6b7280;font-size:.78rem;">MarketplaceJamaah · Real-time AI Platform</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="d-flex align-items-center gap-1" style="font-size:.7rem;color:#065f46;background:#d1fae5;border:1px solid #6ee7b7;padding:4px 12px;border-radius:99px;font-weight:700;">
            <span class="realtime-dot" style="width:6px;height:6px;"></span> Live
        </span>
        <button class="btn btn-sm btn-primary" onclick="refreshStats()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>
</div>

<div class="page-body">

{{-- ══ WA SERVER STATUS BANNER ══ --}}
<div id="wa-status-banner" class="mb-3 p-3 rounded-3"
     style="background:#f0fdf4;border:1.5px solid #6ee7b7;transition:background .4s,border-color .4s;">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0" id="wa-banner-row">
        {{-- WA section --}}
        <div class="d-flex align-items-center gap-3">
            <div id="wa-icon-bg" style="width:44px;height:44px;background:#059669;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .4s;">
                <i class="bi bi-whatsapp" style="color:#fff;font-size:1.2rem;"></i>
            </div>
            <div>
                <div style="font-size:.85rem;font-weight:800;color:#111827;">
                    WA Server &nbsp;<span id="wa-session-label" style="font-weight:600;color:#6b7280;"></span>
                </div>
                <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                    <span id="wa-status-dot" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#9ca3af;flex-shrink:0;"></span>
                    <span id="wa-status-text" style="font-size:.75rem;font-weight:700;color:#059669;">Memeriksa...</span>
                    <span id="wa-uptime" style="font-size:.68rem;color:#9ca3af;"></span>
                    <span id="wa-groups" style="font-size:.68rem;color:#9ca3af;"></span>
                </div>
            </div>
        </div>

        {{-- Divider --}}
        <div style="width:1px;height:40px;background:#e5e7eb;flex-shrink:0;" class="d-none d-md-block"></div>

        {{-- Queue section --}}
        <div class="d-flex align-items-center gap-3">
            <div id="queue-icon-bg" style="width:44px;height:44px;background:#059669;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .4s;">
                <i class="bi bi-cpu-fill" style="color:#fff;font-size:1.1rem;"></i>
            </div>
            <div>
                <div style="font-size:.85rem;font-weight:800;color:#111827;">Queue Worker</div>
                <div class="d-flex align-items-center gap-2 mt-1 flex-wrap">
                    <span id="queue-status-dot" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#9ca3af;flex-shrink:0;"></span>
                    <span id="queue-status-text" style="font-size:.75rem;font-weight:700;color:#059669;">Memeriksa...</span>
                    <span id="queue-pending-badge" style="font-size:.65rem;display:none;font-weight:700;padding:2px 7px;border-radius:99px;"></span>
                    <span id="queue-failed-badge" style="font-size:.65rem;display:none;font-weight:700;padding:2px 7px;border-radius:99px;background:#fee2e2;color:#dc2626;"></span>
                </div>
            </div>
        </div>

        {{-- Buttons --}}
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span id="wa-last-check" style="font-size:.63rem;color:#9ca3af;"></span>
            <button id="btn-queue-restart" onclick="queueRestart()" disabled
                style="background:#7c3aed;color:#fff;border:none;padding:7px 14px;border-radius:8px;font-size:.72rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;opacity:.5;transition:opacity .3s;">
                <i class="bi bi-play-circle-fill"></i><span id="btn-queue-txt">Restart Worker</span>
            </button>
            <button id="btn-wa-restart" onclick="waRestart()" disabled
                style="background:#059669;color:#fff;border:none;padding:7px 14px;border-radius:8px;font-size:.72rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;opacity:.5;transition:opacity .3s;">
                <i class="bi bi-arrow-clockwise"></i><span id="btn-wa-txt">Restart WA</span>
            </button>
        </div>
    </div>

    {{-- Warning strip when queue or WA is broken --}}
    <div id="wa-warning-strip" style="display:none;margin-top:10px;padding:8px 12px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;font-size:.75rem;color:#dc2626;font-weight:600;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="wa-warning-text"></span>
    </div>
</div>

{{-- ══ ROW 1: 4 Big Stat Cards ══ --}}
<div class="row g-3 mb-2">
    <div class="col-6 col-md-3">
        <div class="stat-card card-blue">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon" style="width:36px;height:36px;"><i class="bi bi-chat-dots-fill"></i></div>
                <span class="stat-badge">Pesan</span>
            </div>
            <div class="stat-value" id="stat-total-messages">{{ number_format($stats['total_messages']) }}</div>
            <div class="stat-label">Total Pesan</div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="stat-trend"><i class="bi bi-arrow-up-short"></i>+{{ $stats['today_messages'] }} hari ini</div>
                <span style="font-size:.65rem;color:#a7f3d0;font-weight:600;">{{ $stats['total_groups'] }} grup</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card card-amber">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon" style="width:36px;height:36px;"><i class="bi bi-megaphone-fill"></i></div>
                <span class="stat-badge">Iklan</span>
            </div>
            <div class="stat-value" id="stat-total-ads">{{ number_format($stats['total_ads']) }}</div>
            <div class="stat-label">Iklan Terdeteksi</div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="stat-trend"><i class="bi bi-arrow-up-short"></i>+{{ $stats['today_ads'] }} hari ini</div>
                @php $convRate = $stats['total_ads'] > 0 ? round($stats['total_listings'] / $stats['total_ads'] * 100) : 0; @endphp
                <span style="font-size:.65rem;color:#fde68a;font-weight:600;">{{ $convRate }}% → listing</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card card-emerald">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon" style="width:36px;height:36px;"><i class="bi bi-shop-window"></i></div>
                <span class="stat-badge">Listing</span>
            </div>
            <div class="stat-value" id="stat-total-listings">{{ number_format($stats['total_listings']) }}</div>
            <div class="stat-label">Listing Aktif</div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="stat-trend"><i class="bi bi-arrow-up-short"></i>+{{ $stats['today_listings'] }} hari ini</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card card-rose">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="stat-icon" style="width:36px;height:36px;"><i class="bi bi-people-fill"></i></div>
                <span class="stat-badge">Kontak</span>
            </div>
            <div class="stat-value">{{ number_format($stats['total_contacts']) }}</div>
            <div class="stat-label">Total Anggota</div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="stat-trend"><i class="bi bi-arrow-up-short"></i>+{{ $stats['today_contacts'] }} hari ini</div>
                <span style="font-size:.65rem;color:#fecdd3;font-weight:600;">{{ $stats['registered_count'] }} terdaftar</span>
            </div>
        </div>
    </div>
</div>

{{-- ══ ROW 2: Quick Stat Strip ══ --}}
<div class="row g-2 mb-4">
    <div class="col-6 col-sm-4 col-md">
        <div style="background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#1e1b4b,#312e81);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-trash3-fill" style="color:#a78bfa;font-size:.72rem;"></i>
            </div>
            <div>
                <div id="stat-total-deleted" style="font-size:1.2rem;font-weight:800;color:#4338ca;line-height:1.1;">{{ number_format($stats['total_deleted']) }}</div>
                <div style="font-size:.62rem;color:#6b7280;font-weight:600;margin-top:1px;">Dihapus · +{{ $stats['today_deleted'] }} hari ini</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-md">
        <div style="background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#7f1d1d,#991b1b);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-shield-exclamation" style="color:#fca5a5;font-size:.72rem;"></i>
            </div>
            <div>
                <div id="stat-total-violations" style="font-size:1.2rem;font-weight:800;color:#dc2626;line-height:1.1;">{{ number_format($stats['total_violations']) }}</div>
                <div style="font-size:.62rem;color:#6b7280;font-weight:600;margin-top:1px;">Pelanggaran · +{{ $stats['today_violations'] }} hari ini</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4 col-md">
        <div style="background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:linear-gradient(135deg,#4a1942,#6b21a8);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-person-slash" style="color:#f0abfc;font-size:.72rem;"></i>
            </div>
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:#7c3aed;line-height:1.1;">{{ number_format($stats['total_blocked']) }}</div>
                <div style="font-size:.62rem;color:#6b7280;font-weight:600;margin-top:1px;">Diblokir{{ $stats['total_blocked'] > 0 ? ' · ada user' : ' · aman' }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-md">
        <div style="background:#fff;border:1.5px solid {{ $pendingContacts->count() > 0 ? '#fcd34d' : '#e5e7eb' }};border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:{{ $pendingContacts->count() > 0 ? '#fef3c7' : '#f3f4f6' }};border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-hourglass-split" style="color:{{ $pendingContacts->count() > 0 ? '#d97706' : '#9ca3af' }};font-size:.72rem;"></i>
            </div>
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:{{ $pendingContacts->count() > 0 ? '#d97706' : '#6b7280' }};line-height:1.1;">{{ $pendingContacts->count() }}</div>
                <div style="font-size:.62rem;color:#6b7280;font-weight:600;margin-top:1px;">Pending Approval</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-6 col-md">
        <div style="background:#fff;border:1.5px solid #e5e7eb;border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:10px;">
            <div style="width:32px;height:32px;background:#ecfdf5;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-person-check-fill" style="color:#059669;font-size:.72rem;"></i>
            </div>
            <div>
                <div style="font-size:1.2rem;font-weight:800;color:#059669;line-height:1.1;">{{ $stats['registered_count'] }}</div>
                <div style="font-size:.62rem;color:#6b7280;font-weight:600;margin-top:1px;">Terdaftar</div>
            </div>
        </div>
    </div>
</div>

{{-- ══ CHART ROW: Line chart + Top Kategori ══ --}}
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-2 px-4" style="border-bottom:1px solid #d1fae5;">
                <span style="font-weight:700;color:#111827;font-size:.85rem;">
                    <i class="bi bi-activity me-2" style="color:#059669;"></i>Aktivitas 7 Hari Terakhir
                </span>
                <div class="d-flex align-items-center gap-3" style="font-size:.68rem;color:#6b7280;">
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#059669;margin-right:4px;"></span>Pesan</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:4px;"></span>Iklan</span>
                    <span><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#0d9488;margin-right:4px;"></span>Listing</span>
                </div>
            </div>
            <div class="card-body px-3 pb-3 pt-2">
                <canvas id="activityChart" height="95"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header py-2 px-4" style="border-bottom:1px solid #d1fae5;">
                <span style="font-weight:700;color:#111827;font-size:.85rem;">
                    <i class="bi bi-bar-chart-fill me-2" style="color:#059669;"></i>Top Kategori
                </span>
            </div>
            <div class="card-body px-3 py-2">
                @php
                    $catColors = ['#059669','#f59e0b','#10b981','#e11d48','#0ea5e9','#34d399','#f97316','#06b6d4'];
                    $maxCount = $topCategories->max('listings_count') ?: 1;
                @endphp
                @forelse($topCategories as $i => $cat)
                <div class="d-flex align-items-center gap-2 py-2" style="{{ !$loop->last ? 'border-bottom:1px solid #f0f4ff;' : '' }}">
                    <div style="width:8px;height:8px;border-radius:50%;background:{{ $catColors[$i % count($catColors)] }};flex-shrink:0;"></div>
                    <div style="flex:1;min-width:0;">
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:.75rem;font-weight:600;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $cat->name }}</span>
                            <span style="font-size:.72rem;font-weight:700;color:{{ $catColors[$i % count($catColors)] }};flex-shrink:0;margin-left:6px;">{{ $cat->listings_count }}</span>
                        </div>
                        <div style="height:4px;background:#f0f4ff;border-radius:3px;overflow:hidden;">
                            <div style="height:100%;width:{{ $maxCount > 0 ? ($cat->listings_count / $maxCount * 100) : 0 }}%;background:{{ $catColors[$i % count($catColors)] }};border-radius:3px;transition:width .5s;"></div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-4" style="color:#9ca3af;font-size:.8rem;">Belum ada data kategori</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ══ 3-COLUMN ACTIVITY FEED ══ --}}
<div class="row g-3 mb-4">

    {{-- Recent Messages --}}
    <div class="col-12 col-lg-4">
        <div class="card h-100" style="min-height:0;">
            <div class="card-header d-flex align-items-center justify-content-between py-2 px-4" style="border-bottom:1px solid #d1fae5;">
                <span style="font-weight:700;color:#111827;font-size:.85rem;">
                    <i class="bi bi-chat-dots-fill me-2" style="color:#059669;"></i>Pesan Terbaru
                </span>
                <a href="{{ route('messages.index') }}" style="font-size:.68rem;color:#059669;font-weight:700;text-decoration:none;">Semua →</a>
            </div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                @forelse($recentMessages->take(8) as $msg)
                <div class="d-flex gap-2 align-items-start px-3 py-2" style="{{ !$loop->last ? 'border-bottom:1px solid #f0f4ff;' : '' }}">
                    <div style="width:30px;height:30px;background:{{ $msg->is_ad ? 'linear-gradient(135deg,#d97706,#f59e0b)' : 'linear-gradient(135deg,#059669,#10b981)' }};border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi {{ $msg->is_ad ? 'bi-megaphone-fill' : 'bi-person-fill' }}" style="color:#fff;font-size:.65rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="d-flex align-items-center gap-1">
                            <span style="font-size:.75rem;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:120px;">{{ $msg->sender_name ?: $msg->sender_number }}</span>
                            @if($msg->is_ad)
                            <span style="font-size:.53rem;font-weight:700;background:#fef3c7;color:#d97706;padding:1px 5px;border-radius:5px;">IKLAN</span>
                            @endif
                        </div>
                        <p style="font-size:.7rem;color:#6b7280;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ Str::limit($msg->raw_body, 50) ?: '📷 Media' }}</p>
                        <span style="font-size:.62rem;color:#9ca3af;">{{ $msg->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                @empty
                <div class="text-center py-5" style="color:#9ca3af;font-size:.78rem;">
                    <i class="bi bi-chat-dots" style="font-size:1.5rem;display:block;margin-bottom:.25rem;color:#a7f3d0;"></i>Belum ada pesan
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Listings --}}
    <div class="col-12 col-lg-4">
        <div class="card h-100" style="min-height:0;">
            <div class="card-header d-flex align-items-center justify-content-between py-2 px-4" style="border-bottom:1px solid #d1fae5;">
                <span style="font-weight:700;color:#111827;font-size:.85rem;">
                    <i class="bi bi-shop-window me-2" style="color:#059669;"></i>Listing Terbaru
                </span>
                <a href="{{ route('listings.index') }}" style="font-size:.68rem;color:#059669;font-weight:700;text-decoration:none;">Semua →</a>
            </div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                @forelse($recentListings->take(8) as $listing)
                <div class="d-flex gap-2 align-items-start px-3 py-2" style="{{ !$loop->last ? 'border-bottom:1px solid #f0f4ff;' : '' }}">
                    <div style="width:30px;height:30px;background:linear-gradient(135deg,#059669,#10b981);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 5px rgba(5,150,105,.3);">
                        <i class="{{ $listing->category?->icon ?? 'bi bi-tag-fill' }}" style="color:#fff;font-size:.65rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <span style="font-size:.75rem;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">{{ Str::limit($listing->title, 38) }}</span>
                        <div class="d-flex align-items-center gap-1">
                            <span style="font-size:.72rem;font-weight:700;color:#059669;">{{ $listing->price_formatted }}</span>
                            @if($listing->category)
                            <span style="font-size:.55rem;background:#ecfdf5;color:#059669;padding:1px 5px;border-radius:5px;font-weight:700;">{{ $listing->category->name }}</span>
                            @endif
                        </div>
                        <span style="font-size:.62rem;color:#9ca3af;">{{ $listing->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                @empty
                <div class="text-center py-5" style="color:#9ca3af;font-size:.78rem;">
                    <i class="bi bi-shop" style="font-size:1.5rem;display:block;margin-bottom:.25rem;color:#a7f3d0;"></i>Belum ada listing
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Pending Approval --}}
    <div class="col-12 col-lg-4">
        <div class="card h-100" style="min-height:0;">
            <div class="card-header d-flex align-items-center justify-content-between py-2 px-4" style="border-bottom:1px solid {{ $pendingContacts->count() > 0 ? '#fde68a' : '#e5e7eb' }};">
                <span style="font-weight:700;color:#111827;font-size:.85rem;">
                    <i class="bi bi-hourglass-split me-2" style="color:#d97706;"></i>Pending Approval
                    @if($pendingContacts->count() > 0)
                    <span style="font-size:.6rem;background:#fef3c7;color:#d97706;padding:2px 7px;border-radius:99px;font-weight:700;margin-left:3px;">{{ $pendingContacts->count() }}</span>
                    @endif
                </span>
                @if($pendingContacts->count() > 0)
                <button onclick="resendAllOnboarding()" style="font-size:.62rem;padding:2px 8px;background:#fef3c7;color:#d97706;border:1.5px solid #fcd34d;border-radius:6px;font-weight:700;cursor:pointer;">
                    <i class="bi bi-send me-1"></i>Kirim Semua
                </button>
                @endif
            </div>
            <div class="card-body p-0" style="max-height:420px;overflow-y:auto;">
                @forelse($pendingContacts as $pc)
                @php
                    $isPendingGroup = str_starts_with($pc->onboarding_status ?? '', 'pending_group_approval:');
                    $sc = match(true) {
                        $isPendingGroup => ['color'=>'#ea580c','bg'=>'#fff7ed','label'=>'Minta Gabung'],
                        $pc->onboarding_status === 'pending' => ['color'=>'#d97706','bg'=>'#fef3c7','label'=>'Tunggu Balas'],
                        $pc->onboarding_status === 'pending_seller_products' => ['color'=>'#059669','bg'=>'#ecfdf5','label'=>'Produk (S)'],
                        $pc->onboarding_status === 'pending_buyer_products' => ['color'=>'#0ea5e9','bg'=>'#f0f9ff','label'=>'Produk (B)'],
                        $pc->onboarding_status === 'pending_both_products' => ['color'=>'#7c3aed','bg'=>'#f5f3ff','label'=>'Produk (S+B)'],
                        default => ['color'=>'#6b7280','bg'=>'#f3f4f6','label'=>'Belum DM'],
                    };
                @endphp
                <div class="d-flex gap-2 align-items-center px-3 py-2" id="pending-row-{{ $pc->id }}" style="{{ !$loop->last ? 'border-bottom:1px solid #fef9ee;' : '' }}">
                    <div style="width:30px;height:30px;background:{{ $sc['bg'] }};border:2px solid {{ $sc['color'] }}44;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-person-fill" style="color:{{ $sc['color'] }};font-size:.65rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="d-flex align-items-center gap-1">
                            <span style="font-size:.75rem;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100px;">{{ $pc->name ?: $pc->phone_number }}</span>
                            <span style="font-size:.53rem;font-weight:700;background:{{ $sc['bg'] }};color:{{ $sc['color'] }};padding:1px 5px;border-radius:99px;flex-shrink:0;">{{ $sc['label'] }}</span>
                        </div>
                        <span style="font-size:.62rem;color:#9ca3af;">{{ $pc->phone_number }} · {{ $pc->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="d-flex gap-1 flex-shrink-0">
                        <button onclick="approveContact({{ $pc->id }})" title="Approve" style="background:#ecfdf5;border:1.5px solid #6ee7b7;color:#059669;border-radius:6px;padding:2px 7px;font-size:.65rem;cursor:pointer;">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button onclick="rejectContact({{ $pc->id }})" title="Reject" style="background:#fef2f2;border:1.5px solid #fca5a5;color:#dc2626;border-radius:6px;padding:2px 7px;font-size:.65rem;cursor:pointer;">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <button onclick="resendOnboarding({{ $pc->id }})" title="Kirim ulang DM" style="background:none;border:1.5px solid #fcd34d;color:#d97706;border-radius:6px;padding:2px 7px;font-size:.65rem;cursor:pointer;">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
                @empty
                <div class="text-center py-5" style="color:#9ca3af;font-size:.78rem;">
                    <i class="bi bi-check-circle" style="font-size:1.5rem;display:block;margin-bottom:.25rem;color:#a7f3d0;"></i>Semua sudah onboarding 🎉
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ══ MODERATION SECTION ══ --}}
<div class="d-flex align-items-center gap-2 mb-3">
    <div style="width:4px;height:18px;background:linear-gradient(#dc2626,#f87171);border-radius:4px;"></div>
    <span style="font-weight:700;color:#111827;font-size:.9rem;">Moderasi &amp; Pelanggaran</span>
    <span style="font-size:.7rem;color:#9ca3af;">Log pesan dihapus dan user pelanggar</span>
</div>

<div class="row g-3 mb-4">
    {{-- Deleted Messages --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-2 px-4" style="border-bottom:2px solid #f3e8ff;">
                <span style="font-weight:700;color:#111827;font-size:.85rem;">
                    <i class="bi bi-trash3-fill me-2" style="color:#7c3aed;"></i>Pesan Dihapus Terbaru
                </span>
                <span style="font-size:.62rem;font-weight:700;background:#f3e8ff;color:#7c3aed;padding:2px 9px;border-radius:99px;">{{ $stats['total_deleted'] }} total</span>
            </div>
            <div class="card-body p-0" style="max-height:340px;overflow-y:auto;">
                @forelse($recentDeleted as $msg)
                <div class="d-flex gap-2 align-items-start px-3 py-2" style="{{ !$loop->last ? 'border-bottom:1px solid #faf5ff;' : '' }}">
                    <div style="width:30px;height:30px;background:#fdf4ff;border:2px solid #e9d5ff;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-person-fill" style="color:#9333ea;font-size:.7rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="d-flex align-items-center gap-1 mb-0">
                            <span style="font-size:.75rem;font-weight:700;color:#111827;">{{ $msg->sender_name ?: $msg->sender_number }}</span>
                            @php
                                $cl = match($msg->message_category) {
                                    'non_ad_deleted' => ['label'=>'Bukan Iklan','color'=>'#7c3aed','bg'=>'#ede9fe'],
                                    'extraction_failed' => ['label'=>'Gagal Ekstrak','color'=>'#d97706','bg'=>'#fef3c7'],
                                    default => ['label'=>($msg->message_category ?? '-'),'color'=>'#6b7280','bg'=>'#f3f4f6'],
                                };
                            @endphp
                            <span style="font-size:.53rem;font-weight:700;background:{{ $cl['bg'] }};color:{{ $cl['color'] }};padding:1px 5px;border-radius:99px;">{{ $cl['label'] }}</span>
                        </div>
                        <p style="font-size:.72rem;color:#6b7280;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ Str::limit($msg->raw_body, 65) ?: '(tidak ada teks)' }}</p>
                        <span style="font-size:.62rem;color:#9ca3af;">{{ $msg->created_at->diffForHumans() }}{{ $msg->group ? ' · '.$msg->group->group_name : '' }}</span>
                    </div>
                </div>
                @empty
                <div class="text-center py-5" style="color:#9ca3af;font-size:.78rem;">
                    <i class="bi bi-check-circle" style="font-size:1.5rem;display:block;margin-bottom:.25rem;color:#a7f3d0;"></i>Tidak ada pesan yang dihapus
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Violators --}}
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between py-2 px-4" style="border-bottom:2px solid #fee2e2;">
                <span style="font-weight:700;color:#111827;font-size:.85rem;">
                    <i class="bi bi-shield-exclamation me-2" style="color:#dc2626;"></i>User Pelanggar
                </span>
                <span style="font-size:.62rem;font-weight:700;background:#fee2e2;color:#dc2626;padding:2px 9px;border-radius:99px;">{{ $violators->count() }} user</span>
            </div>
            <div class="card-body p-2" style="max-height:340px;overflow-y:auto;">
                @forelse($violators as $v)
                @php
                    $rl = $v->is_blocked ? 'blocked' : ($v->total_violations >= 3 ? 'high' : ($v->total_violations >= 1 ? 'medium' : 'low'));
                    $rc = match($rl) {
                        'blocked' => ['bg'=>'#fef2f2','border'=>'#fca5a5','color'=>'#dc2626','badge'=>'DIBLOKIR'],
                        'high'    => ['bg'=>'#fff7ed','border'=>'#fdba74','color'=>'#ea580c','badge'=>'RISIKO TINGGI'],
                        'medium'  => ['bg'=>'#fefce8','border'=>'#fde047','color'=>'#d97706','badge'=>'PERINGATAN'],
                        default   => ['bg'=>'#f9fafb','border'=>'#e5e7eb','color'=>'#6b7280','badge'=>'MINOR'],
                    };
                @endphp
                <div class="d-flex gap-2 align-items-center p-2 mb-1 rounded-3" style="background:{{ $rc['bg'] }};border:1.5px solid {{ $rc['border'] }};">
                    <div style="width:36px;height:36px;background:{{ $rc['color'] }};border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi {{ $rl === 'blocked' ? 'bi-person-slash' : 'bi-person-fill' }}" style="color:#fff;font-size:.75rem;"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div class="d-flex align-items-center gap-1">
                            <span style="font-size:.78rem;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:130px;">{{ $v->name ?: $v->phone_number }}</span>
                            <span style="font-size:.53rem;font-weight:700;background:{{ $rc['color'] }};color:#fff;padding:1px 6px;border-radius:99px;flex-shrink:0;">{{ $rc['badge'] }}</span>
                        </div>
                        <span style="font-size:.65rem;color:#6b7280;">{{ $v->phone_number }}</span>
                        @if($v->last_violation_message)
                        <div style="font-size:.65rem;color:#6b7280;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ Str::limit($v->last_violation_message->raw_body, 50) }}</div>
                        @endif
                    </div>
                    <div class="d-flex gap-2 text-center flex-shrink-0">
                        <div style="background:rgba(0,0,0,.05);border-radius:7px;padding:3px 8px;min-width:38px;">
                            <div style="font-size:.9rem;font-weight:800;color:{{ $rc['color'] }};line-height:1.1;">{{ $v->warning_count }}</div>
                            <div style="font-size:.53rem;color:#9ca3af;">⚠️</div>
                        </div>
                        <div style="background:rgba(0,0,0,.05);border-radius:7px;padding:3px 8px;min-width:38px;">
                            <div style="font-size:.9rem;font-weight:800;color:{{ $rc['color'] }};line-height:1.1;">{{ $v->total_violations }}</div>
                            <div style="font-size:.53rem;color:#9ca3af;">🚫</div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-5" style="color:#9ca3af;font-size:.78rem;">
                    <i class="bi bi-shield-check" style="font-size:1.5rem;display:block;margin-bottom:.25rem;color:#a7f3d0;"></i>Tidak ada user pelanggar 🎉
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

</div>
@endsection

@push('scripts')
<script>
const chartLabels = @json($chartData->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d M')));
const chartMessages = @json($chartData->pluck('messages'));
const chartAds = @json($chartData->pluck('ads'));
const chartListings = @json($chartData->pluck('listings'));
const catLabels = @json($topCategories->pluck('name'));
const catCounts = @json($topCategories->pluck('listings_count'));

// ── Line chart ─────────────────────────────
const ctxLine = document.getElementById('activityChart').getContext('2d');
const gradBlue = ctxLine.createLinearGradient(0, 0, 0, 200);
gradBlue.addColorStop(0, 'rgba(5,150,105,.3)');
gradBlue.addColorStop(1, 'rgba(5,150,105,.0)');
const gradAmber = ctxLine.createLinearGradient(0, 0, 0, 200);
gradAmber.addColorStop(0, 'rgba(245,158,11,.2)');
gradAmber.addColorStop(1, 'rgba(245,158,11,.0)');
const gradTeal = ctxLine.createLinearGradient(0, 0, 0, 200);
gradTeal.addColorStop(0, 'rgba(13,148,136,.2)');
gradTeal.addColorStop(1, 'rgba(13,148,136,.0)');

new Chart(ctxLine, {
    type: 'line',
    data: {
        labels: chartLabels.length ? chartLabels : ['Sen','Sel','Rab','Kam','Jum','Sab','Min'],
        datasets: [
            { label:'Pesan',   data: chartMessages, borderColor:'#059669', backgroundColor:gradBlue,  tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#059669', pointBorderColor:'#fff', pointBorderWidth:2, borderWidth:2 },
            { label:'Iklan',   data: chartAds,      borderColor:'#f59e0b', backgroundColor:gradAmber, tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#f59e0b', pointBorderColor:'#fff', pointBorderWidth:2, borderWidth:2 },
            { label:'Listing', data: chartListings, borderColor:'#0d9488', backgroundColor:gradTeal,  tension:.4, fill:true, pointRadius:3, pointBackgroundColor:'#0d9488', pointBorderColor:'#fff', pointBorderWidth:2, borderWidth:2 },
        ]
    },
    options: {
        responsive: true,
        interaction: { mode:'index', intersect:false },
        plugins: {
            legend: { display:false },
            tooltip: { backgroundColor:'#022c22', titleColor:'#6ee7b7', bodyColor:'#a7f3d0', cornerRadius:8, padding:8 }
        },
        scales: {
            x: { ticks:{ color:'#9ca3af', font:{size:10} }, grid:{ color:'#f0fdf4' } },
            y: { ticks:{ color:'#9ca3af', font:{size:10} }, grid:{ color:'#f0fdf4' } }
        }
    }
});

// ── WA Server Status ─────────────────────
const _waPhoneId = '{{ config("services.wa_gateway.phone_id") }}';
let _waCurrentSessionId = null;
let _waStatus = 'unknown';
let _queueWorkerRunning = false;
let _queuePending = 0;

function pollAll() {
    const t = Date.now();
    Promise.allSettled([
        fetch('{{ route("dashboard.wa-status") }}').then(r => r.json()),
        fetch('{{ route("dashboard.queue-status") }}').then(r => r.json()),
    ]).then(([waRes, qRes]) => {
        document.getElementById('wa-last-check').textContent = 'cek ' + new Date().toLocaleTimeString('id-ID', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
        if (waRes.status === 'fulfilled') _applyWaStatus(waRes.value);
        else _applyWaStatus({ error: 'Timeout' });
        if (qRes.status === 'fulfilled') _applyQueueStatus(qRes.value);
        else _applyQueueStatus({ worker_running: false, pending: 0, failed: 0 });
        _updateWarningStrip();
    });
}

function _fmtUptime(sec) {
    sec = Math.floor(sec);
    if (sec < 60) return sec + 'd';
    if (sec < 3600) return Math.floor(sec/60) + 'm';
    if (sec < 86400) return Math.floor(sec/3600) + 'j';
    return Math.floor(sec/86400) + 'h';
}

function _applyWaStatus(data) {
    const sessions = data.sessions || {};
    const ids = Object.keys(sessions);
    if (!ids.length) { _setWaBanner('disconnected', 'Tidak ada sesi', '', ''); return; }
    const id = ids.includes(_waPhoneId) ? _waPhoneId : ids[0];
    _waCurrentSessionId = id;
    const s = sessions[id];
    _waStatus = s.status;
    const uptime = data.uptime ? _fmtUptime(data.uptime) + ' uptime' : '';
    const groups = s.groups_cached ? s.groups_cached + ' grup' : '';
    _setWaBanner(s.status, s.label || id, uptime, groups);
}

function _setWaBanner(status, label, uptime, groups) {
    const dot  = document.getElementById('wa-status-dot');
    const txt  = document.getElementById('wa-status-text');
    const lbl  = document.getElementById('wa-session-label');
    const up   = document.getElementById('wa-uptime');
    const grp  = document.getElementById('wa-groups');
    const btn  = document.getElementById('btn-wa-restart');
    const icon = document.getElementById('wa-icon-bg');

    lbl.textContent = label ? '· ' + label : '';
    up.textContent  = uptime;
    grp.textContent = groups;

    const cfg = {
        open:         { dot:'#16a34a', txt:'Terhubung ✅',          icon:'#059669', txtColor:'#16a34a' },
        connecting:   { dot:'#f59e0b', txt:'Menghubungkan…',        icon:'#d97706', txtColor:'#d97706' },
        disconnected: { dot:'#dc2626', txt:'Terputus ❌',            icon:'#dc2626', txtColor:'#dc2626' },
        error:        { dot:'#9ca3af', txt:'Tidak dapat terhubung', icon:'#6b7280', txtColor:'#6b7280' },
    };
    const c = cfg[status] || cfg.error;
    dot.style.background  = c.dot;
    txt.textContent       = c.txt;
    txt.style.color       = c.txtColor;
    icon.style.background = c.icon;

    const canRestart = (status === 'disconnected' || status === 'error') && _waCurrentSessionId;
    btn.disabled = !canRestart;
    btn.style.opacity = canRestart ? '1' : '.45';
    btn.style.cursor  = canRestart ? 'pointer' : 'default';
}

function _applyQueueStatus(data) {
    _queueWorkerRunning = !!data.worker_running;
    _queuePending = data.pending || 0;
    const failed  = data.failed || 0;
    const stuck   = data.stuck  || 0;

    const dot    = document.getElementById('queue-status-dot');
    const txt    = document.getElementById('queue-status-text');
    const icon   = document.getElementById('queue-icon-bg');
    const pBadge = document.getElementById('queue-pending-badge');
    const fBadge = document.getElementById('queue-failed-badge');
    const btn    = document.getElementById('btn-queue-restart');

    if (_queueWorkerRunning && failed === 0 && stuck === 0) {
        dot.style.background  = '#16a34a';
        txt.textContent       = 'Running ✅';
        txt.style.color       = '#16a34a';
        icon.style.background = '#059669';
    } else if (_queueWorkerRunning) {
        dot.style.background  = '#f59e0b';
        txt.textContent       = 'Running (ada masalah)';
        txt.style.color       = '#d97706';
        icon.style.background = '#d97706';
    } else {
        dot.style.background  = '#dc2626';
        txt.textContent       = 'MATI ❌ — bot tidak respon!';
        txt.style.color       = '#dc2626';
        icon.style.background = '#dc2626';
    }

    if (_queuePending > 0) {
        pBadge.style.display     = 'inline-block';
        pBadge.textContent       = _queuePending + ' pending';
        pBadge.style.background  = _queuePending > 10 ? '#fef3c7' : '#f0fdf4';
        pBadge.style.color       = _queuePending > 10 ? '#d97706' : '#059669';
    } else {
        pBadge.style.display = 'none';
    }

    if (failed > 0) {
        fBadge.style.display  = 'inline-block';
        fBadge.textContent    = failed + ' failed';
    } else {
        fBadge.style.display  = 'none';
    }

    const needRestart = !_queueWorkerRunning || failed > 0 || stuck > 0 || _queuePending > 20;
    btn.disabled      = false; // always allow manual restart
    btn.style.opacity = '1';
    btn.style.cursor  = 'pointer';
    btn.style.background = needRestart ? '#dc2626' : '#7c3aed';
}

function _updateWarningStrip() {
    const strip = document.getElementById('wa-warning-strip');
    const warnTxt = document.getElementById('wa-warning-text');
    const msgs = [];
    if (_waStatus === 'disconnected' || _waStatus === 'error')
        msgs.push('WA Server terputus — klik Restart WA.');
    if (!_queueWorkerRunning)
        msgs.push('Queue Worker MATI — bot tidak bisa membalas pesan! Klik Restart Worker.');
    if (_queuePending > 20)
        msgs.push(_queuePending + ' pesan antri menunggu diproses.');
    if (msgs.length) {
        warnTxt.textContent  = msgs.join('  |  ');
        strip.style.display  = 'block';
    } else {
        strip.style.display  = 'none';
    }
    // Update overall banner color
    const banner = document.getElementById('wa-status-banner');
    if (msgs.length) {
        banner.style.background   = '#fef2f2';
        banner.style.borderColor  = '#fca5a5';
    } else {
        banner.style.background   = '#f0fdf4';
        banner.style.borderColor  = '#6ee7b7';
    }
}

function waRestart() {
    if (!_waCurrentSessionId) return;
    const btn = document.getElementById('btn-wa-restart');
    const lbl = document.getElementById('btn-wa-txt');
    btn.disabled = true; lbl.textContent = 'Memulai...';
    fetch(`/dashboard/wa-restart/${encodeURIComponent(_waCurrentSessionId)}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(d => {
        lbl.textContent = 'Restart WA';
        if (d.ok) {
            document.getElementById('wa-status-text').textContent = 'Restart dikirim — Menunggu…';
            let n = 0; const p = setInterval(() => { pollAll(); if (++n >= 10) clearInterval(p); }, 3000);
        } else { alert('❌ WA Restart gagal: ' + (d.error || d.message || 'unknown')); }
    })
    .catch(() => { lbl.textContent = 'Restart WA'; alert('❌ Gagal terhubung ke gateway'); });
}

function queueRestart() {
    const btn = document.getElementById('btn-queue-restart');
    const lbl = document.getElementById('btn-queue-txt');
    btn.disabled = true; lbl.textContent = 'Memulai...';
    fetch('{{ route("dashboard.queue-restart") }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(d => {
        lbl.textContent = 'Restart Worker';
        btn.disabled = false;
        if (d.ok) {
            const steps = (d.steps || []).join('\n');
            alert('✅ Queue Worker restart:\n\n' + steps + '\n\nPending: ' + d.pending + ', Failed: ' + d.failed);
            setTimeout(pollAll, 2000);
        } else { alert('❌ Gagal: ' + (d.error || JSON.stringify(d))); }
    })
    .catch(() => { lbl.textContent = 'Restart Worker'; btn.disabled = false; alert('❌ Request gagal'); });
}

pollAll();
setInterval(pollAll, 20000);

// ── Auto refresh ──────────────────────────
function refreshStats() {
    fetch('{{ route("dashboard.stats") }}')
        .then(r => r.json())
        .then(d => {
            document.getElementById('stat-total-messages').textContent = d.total_messages.toLocaleString('id-ID');
            document.getElementById('stat-total-ads').textContent = d.total_ads.toLocaleString('id-ID');
            document.getElementById('stat-total-listings').textContent = d.total_listings.toLocaleString('id-ID');
            if (document.getElementById('stat-total-deleted')) document.getElementById('stat-total-deleted').textContent = d.total_deleted.toLocaleString('id-ID');
            if (document.getElementById('stat-total-violations')) document.getElementById('stat-total-violations').textContent = d.total_violations.toLocaleString('id-ID');
        });
}
setInterval(refreshStats, 30000);

// ── Resend onboarding ──────────────────────
function resendOnboarding(contactId) {
    if (!confirm('Kirim ulang DM onboarding ke kontak ini?')) return;
    fetch(`/contacts/${contactId}/resend-onboarding`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { alert('✅ ' + d.message); location.reload(); }
        else { alert('❌ ' + (d.message || 'Gagal kirim')); }
    })
    .catch(() => alert('❌ Gagal mengirim, coba lagi.'));
}

function resendAllOnboarding() {
    if (!confirm('Kirim ulang DM onboarding ke SEMUA kontak pending?')) return;
    fetch('/contacts/resend-onboarding-all', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { alert('✅ ' + d.message); location.reload(); }
        else { alert('❌ ' + (d.message || 'Gagal kirim')); }
    })
    .catch(() => alert('❌ Gagal mengirim, coba lagi.'));
}

function approveContact(contactId) {
    if (!confirm('Approve kontak ini dan masukkan ke grup?')) return;
    const row = document.getElementById('pending-row-' + contactId);
    if (row) row.style.opacity = '0.5';
    fetch(`/contacts/${contactId}/approve`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (row) row.remove();
            alert('✅ ' + d.message);
        } else {
            if (row) row.style.opacity = '1';
            alert('❌ ' + (d.message || 'Gagal approve'));
        }
    })
    .catch(() => { if (row) row.style.opacity = '1'; alert('❌ Gagal approve, coba lagi.'); });
}

function rejectContact(contactId) {
    if (!confirm('Reject kontak ini? Kontak akan diblokir.')) return;
    const row = document.getElementById('pending-row-' + contactId);
    if (row) row.style.opacity = '0.5';
    fetch(`/contacts/${contactId}/reject`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (row) row.remove();
            alert('✅ ' + d.message);
        } else {
            if (row) row.style.opacity = '1';
            alert('❌ ' + (d.message || 'Gagal reject'));
        }
    })
    .catch(() => { if (row) row.style.opacity = '1'; alert('❌ Gagal reject, coba lagi.'); });
}
</script>
@endpush
