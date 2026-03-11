@extends('layouts.public')
@section('title', 'Dashboard Saya — Marketplace Jamaah')

@section('styles')
<style>
    .member-hero { background:linear-gradient(135deg,#022c22,#064e3b); padding:2.5rem 0; color:#fff; }
    .member-avatar { width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#059669,#34d399);display:flex;align-items:center;justify-content:center;font-size:1.6rem;font-weight:800;color:#fff;border:3px solid rgba(255,255,255,.25); }
    .stat-box { background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:14px;padding:1rem 1.5rem;text-align:center;backdrop-filter:blur(8px); }
    .stat-box .num { font-size:1.8rem;font-weight:800;color:#6ee7b7; }
    .stat-box .lbl { font-size:.75rem;color:rgba(255,255,255,.65);margin-top:.1rem; }
    .listing-card { background:#fff;border:1px solid #d1fae5;border-radius:14px;overflow:hidden;transition:all .2s;color:inherit;display:block; }
    .listing-card:hover { transform:translateY(-3px);box-shadow:0 8px 24px rgba(5,150,105,.12); }
    .btn-edit-listing { display:block;width:100%;text-align:center;background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;border-radius:0 0 13px 13px;font-size:.75rem;font-weight:700;padding:.45rem .5rem;text-decoration:none;transition:background .15s; }
    .btn-edit-listing:hover { background:#d1fae5;color:#047857; }
    .listing-img { width:100%;height:150px;object-fit:cover; }
    .listing-body { padding:.9rem; }
    .status-active { background:#dcfce7;color:#15803d;border-radius:6px;font-size:.68rem;font-weight:700;padding:.15rem .5rem; }
    .status-sold { background:#f3f4f6;color:#6b7280;border-radius:6px;font-size:.68rem;font-weight:700;padding:.15rem .5rem; }
    .btn-logout { background:transparent;border:1.5px solid rgba(255,255,255,.3);color:rgba(255,255,255,.7);border-radius:8px;font-size:.78rem;font-weight:600;padding:.35rem .9rem;cursor:pointer;transition:all .15s; }
    .btn-logout:hover { border-color:rgba(255,255,255,.6);color:#fff; }
</style>
@endsection

@section('content')
<div class="member-hero">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="member-avatar">{{ mb_strtoupper(mb_substr($contact->name ?? 'P', 0, 1)) }}</div>
                <div>
                    <h1 style="font-size:1.4rem;font-weight:800;color:#fff;margin-bottom:.15rem;">Halo, {{ $contact->name ?? $contact->phone_number }}! 👋</h1>
                    <div style="color:rgba(255,255,255,.65);font-size:.82rem;"><i class="bi bi-phone me-1"></i>+{{ $contact->phone_number }}</div>
                    @if($contact->member_role)
                        <div style="font-size:.75rem;margin-top:.25rem;">
                            @if($contact->member_role === 'seller') <span style="background:rgba(110,231,183,.2);color:#6ee7b7;border-radius:6px;padding:.15rem .5rem;font-weight:700;">🏪 Penjual</span>
                            @elseif($contact->member_role === 'buyer') <span style="background:rgba(110,231,183,.2);color:#6ee7b7;border-radius:6px;padding:.15rem .5rem;font-weight:700;">🛍️ Pembeli</span>
                            @else <span style="background:rgba(110,231,183,.2);color:#6ee7b7;border-radius:6px;padding:.15rem .5rem;font-weight:700;">🏪🛍️ Penjual & Pembeli</span>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('wa.logout') }}">
                @csrf
                <button type="submit" class="btn-logout"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
            </form>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-4 col-md-2">
                <div class="stat-box">
                    <div class="num">{{ $listings->total() }}</div>
                    <div class="lbl">Total Iklan</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="stat-box">
                    <div class="num">{{ $listings->where('status','active')->count() }}</div>
                    <div class="lbl">Aktif</div>
                </div>
            </div>
            <div class="col-4 col-md-2">
                <div class="stat-box">
                    <div class="num">{{ $listings->where('status','sold')->count() }}</div>
                    <div class="lbl">Terjual</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    @if(session('success'))<div class="alert alert-success rounded-3 mb-3">{{ session('success') }}</div>@endif

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 style="font-weight:800;color:#111827;margin:0;">Iklan Saya</h4>
        <a href="{{ route('public.seller', $contact->phone_number) }}" class="btn btn-sm" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;border-radius:8px;font-weight:600;">
            <i class="bi bi-eye me-1"></i>Lihat Profil Publik
        </a>
    </div>

    @if($listings->isEmpty())
        <div class="text-center py-5" style="color:#6b7280;">
            <i class="bi bi-megaphone" style="font-size:3rem;color:#a7f3d0;display:block;margin-bottom:1rem;"></i>
            <div style="font-size:.95rem;">Belum ada iklan.</div>
            <div style="font-size:.82rem;margin-top:.5rem;">Post iklan di grup WhatsApp untuk mulai berjualan!</div>
        </div>
    @else
        <div class="row g-3">
            @foreach($listings as $listing)
            <div class="col-6 col-md-4 col-lg-3">
                <div class="listing-card">
                    <a href="{{ route('public.listing', $listing->id) }}" style="text-decoration:none;color:inherit;display:block;">
                    @php $img = $listing->media_urls[0] ?? null; @endphp
                    @if($img && preg_match('/\.(mp4|mov|webm)$/i', $img))
                        <video src="{{ $img }}" class="listing-img" muted style="object-fit:cover;"></video>
                    @elseif($img)
                        <img src="{{ $img }}" class="listing-img" alt="{{ $listing->title }}">
                    @else
                        <div class="listing-img d-flex align-items-center justify-content-center" style="background:#ecfdf5;"><i class="bi bi-image" style="color:#a7f3d0;font-size:2.5rem;"></i></div>
                    @endif
                    <div class="listing-body">
                        @if($listing->category)<span style="font-size:.65rem;font-weight:700;color:#059669;background:#ecfdf5;border-radius:5px;padding:.1rem .4rem;">{{ $listing->category->name }}</span>@endif
                        <div style="font-size:.82rem;font-weight:700;color:#111827;margin-top:.3rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $listing->title }}</div>
                        <div style="font-size:.88rem;font-weight:800;color:#059669;margin-top:.2rem;">{{ $listing->price_formatted }}</div>
                        <div class="mt-1">
                            <span class="{{ $listing->status === 'active' ? 'status-active' : 'status-sold' }}">{{ ucfirst($listing->status) }}</span>
                        </div>
                    </div>
                    </a>
                    <a href="{{ route('member.listing.edit', $listing->id) }}" class="btn-edit-listing"><i class="bi bi-pencil-fill me-1"></i>Edit Iklan</a>
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-4 d-flex justify-content-center">
            {{ $listings->links() }}
        </div>
    @endif
</div>
@endsection
