@extends('layouts.public')
@section('title', ($contact->name ?? 'Penjual') . ' — Marketplace Jamaah')

@section('styles')
<style>
    .seller-hero { background:linear-gradient(135deg,#022c22,#064e3b); padding:3rem 0 2rem; color:#fff; }
    .seller-big-avatar { width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#059669,#34d399);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#fff;border:3px solid rgba(255,255,255,.3); }
    .listing-card { background:#fff;border:1px solid #d1fae5;border-radius:14px;overflow:hidden;transition:all .2s;text-decoration:none;color:inherit;display:block; }
    .listing-card:hover { transform:translateY(-3px);box-shadow:0 8px 24px rgba(5,150,105,.12); }
    .listing-img { width:100%;height:160px;object-fit:cover; }
    .listing-body { padding:.9rem; }
    .listing-title { font-size:.85rem;font-weight:700;color:#111827;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.4; }
    .listing-price { font-size:.95rem;font-weight:800;color:#059669;margin-top:.3rem; }
</style>
@endsection

@section('content')
<div class="seller-hero">
    <div class="container">
        <div class="d-flex align-items-center gap-4">
            <div class="seller-big-avatar">{{ mb_strtoupper(mb_substr($contact->name ?? 'P', 0, 1)) }}</div>
            <div>
                <h1 style="font-size:1.6rem;font-weight:800;color:#fff;margin-bottom:.25rem;">{{ $contact->name ?? $contact->phone_number }}</h1>
                @if($contact->sell_products)
                    <div style="color:#6ee7b7;font-size:.88rem;">🏪 Menjual: {{ $contact->sell_products }}</div>
                @endif
                <div class="d-flex gap-3 mt-2" style="font-size:.8rem;color:rgba(255,255,255,.65);">
                    <span><i class="bi bi-tags me-1"></i>{{ $listings->total() }} produk</span>
                    @if($contact->member_role === 'both')<span><i class="bi bi-person-check me-1"></i>Penjual & Pembeli</span>
                    @elseif($contact->member_role === 'seller')<span><i class="bi bi-shop me-1"></i>Penjual</span>@endif
                </div>
                <div class="mt-2">
                    <a href="https://wa.me/{{ $contact->phone_number }}?text={{ urlencode('Halo, saya menemukan profil Anda di Marketplace Jamaah.') }}"
                       target="_blank" class="btn btn-sm" style="background:rgba(37,211,102,.25);border:1px solid rgba(37,211,102,.4);color:#6ee7b7;border-radius:8px;font-weight:600;font-size:.78rem;">
                        <i class="bi bi-whatsapp"></i> Chat via WA
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    @if($listings->isEmpty())
        <div class="text-center py-5" style="color:#6b7280;">
            <i class="bi bi-box-seam" style="font-size:3rem;color:#a7f3d0;display:block;margin-bottom:1rem;"></i>
            Belum ada produk aktif dari penjual ini.
        </div>
    @else
        <div class="row g-3">
            @foreach($listings as $listing)
            <div class="col-6 col-md-4 col-lg-3">
                <a href="{{ route('public.listing', $listing->id) }}" class="listing-card">
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
                        <div class="listing-title mt-1">{{ $listing->title }}</div>
                        <div class="listing-price">{{ $listing->price_formatted }}</div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
        <div class="mt-4 d-flex justify-content-center">
            {{ $listings->links() }}
        </div>
    @endif
</div>
@endsection
