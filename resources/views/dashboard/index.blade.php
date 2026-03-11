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
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto;">
                @forelse($pendingContacts->take(8) as $pc)
                @php
                    $sc = match($pc->onboarding_status) {
                        'pending' => ['color'=>'#d97706','bg'=>'#fef3c7','label'=>'Tunggu Balas'],
                        'pending_seller_products' => ['color'=>'#059669','bg'=>'#ecfdf5','label'=>'Produk (S)'],
                        'pending_buyer_products' => ['color'=>'#0ea5e9','bg'=>'#f0f9ff','label'=>'Produk (B)'],
                        'pending_both_products' => ['color'=>'#7c3aed','bg'=>'#f5f3ff','label'=>'Produk (S+B)'],
                        default => ['color'=>'#6b7280','bg'=>'#f3f4f6','label'=>'Belum DM'],
                    };
                @endphp
                <div class="d-flex gap-2 align-items-center px-3 py-2" style="{{ !$loop->last ? 'border-bottom:1px solid #fef9ee;' : '' }}">
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
                    <button onclick="resendOnboarding({{ $pc->id }})" title="Kirim ulang DM" style="background:none;border:1.5px solid #fcd34d;color:#d97706;border-radius:6px;padding:2px 7px;font-size:.65rem;cursor:pointer;flex-shrink:0;">
                        <i class="bi bi-send"></i>
                    </button>
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
</script>
@endpush
