@extends('layouts.public')
@section('title', $listing->title . ' — Marketplace Jamaah')
@section('description', Str::limit(strip_tags($listing->description ?? $listing->title), 160))

@section('styles')
@php $ogImage = ($listing->media_urls[0] ?? null); @endphp
<meta property="og:title" content="{{ $listing->title }}">
<meta property="og:description" content="{{ Str::limit(strip_tags($listing->description ?? $listing->title), 160) }}">
@if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
<meta property="og:url" content="{{ url('/p/' . $listing->id) }}">
<meta property="og:type" content="product">
<style>
    .detail-hero { display:none; }
    .breadcrumb-strip { background:#fff; border-bottom:1.5px solid #d1fae5; padding:.55rem 0; }
    .media-gallery img, .media-gallery video { width:100%; height:340px; object-fit:cover; border-radius:16px; cursor:zoom-in; }
    .media-thumb { width:72px; height:72px; object-fit:cover; border-radius:10px; cursor:pointer; border:2.5px solid transparent; transition:all .15s; }
    .media-thumb.active, .media-thumb:hover { border-color:#059669; }
    .price-tag { font-size:1.9rem; font-weight:800; color:#059669; }
    .detail-card { background:#fff; border:1px solid #d1fae5; border-radius:18px; padding:1.5rem; box-shadow:0 2px 12px rgba(5,150,105,.08); margin-bottom:1.2rem; }
    .badge-category { background:#ecfdf5; color:#059669; border:1px solid #a7f3d0; border-radius:8px; font-size:.75rem; font-weight:700; padding:.25rem .7rem; }
    .badge-condition { background:#f3f4f6; color:#374151; border-radius:8px; font-size:.75rem; font-weight:600; padding:.25rem .6rem; }
    .seller-card { background:linear-gradient(135deg,#ecfdf5,#f0fdf4); border:1.5px solid #a7f3d0; border-radius:18px; padding:1.5rem; }
    .seller-avatar { width:56px; height:56px; border-radius:50%; background:linear-gradient(135deg,#059669,#34d399); display:flex; align-items:center; justify-content:center; font-size:1.4rem; font-weight:800; color:#fff; flex-shrink:0; }
    .btn-wa { background:linear-gradient(135deg,#25d366,#128c7e); color:#fff; border:none; border-radius:12px; padding:.7rem 1.5rem; font-weight:700; font-size:.95rem; display:inline-flex; align-items:center; gap:.5rem; text-decoration:none; transition:all .2s; }
    .btn-wa:hover { color:#fff; transform:translateY(-2px); box-shadow:0 6px 18px rgba(37,211,102,.4); }
    .related-card { background:#fff; border:1px solid #d1fae5; border-radius:14px; overflow:hidden; transition:all .2s; text-decoration:none; color:inherit; display:block; }
    .related-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(5,150,105,.12); }
    .related-img { width:100%; height:140px; object-fit:cover; }
</style>
@endsection

@section('content')
{{-- Breadcrumb --}}
<div class="breadcrumb-strip">
    <div class="container">
        <nav style="font-size:.8rem;" aria-label="breadcrumb">
            <ol class="breadcrumb mb-0" style="--bs-breadcrumb-divider:'›';">
                <li class="breadcrumb-item"><a href="{{ route('landing') }}" style="color:#059669;font-weight:600;">Beranda</a></li>
                @if($listing->category)
                <li class="breadcrumb-item"><a href="{{ route('landing', ['category_id' => $listing->category_id]) }}" style="color:#059669;font-weight:600;">{{ $listing->category->name }}</a></li>
                @endif
                <li class="breadcrumb-item active" style="color:#6b7280;">{{ Str::limit($listing->title, 50) }}</li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">
        {{-- Left: Media + Description --}}
        <div class="col-12 col-lg-8">
            {{-- Media gallery --}}
            <div class="detail-card mb-3">
                @php $media = $listing->media_urls ?? []; @endphp
                @if(count($media))
                    <div class="media-gallery mb-2" id="mainMediaWrap">
                        @php $first = $media[0]; $isVideo = str_contains(strtolower($first), '.mp4') || str_contains(strtolower($first), '.webm'); @endphp
                        @if($isVideo)
                            <video id="mainMedia" src="{{ $first }}" controls style="width:100%;height:340px;object-fit:cover;border-radius:16px;"></video>
                        @else
                            <img id="mainMedia" src="{{ $first }}" alt="{{ $listing->title }}" style="width:100%;height:340px;object-fit:cover;border-radius:16px;cursor:zoom-in;" onclick="window.open(this.src,'_blank')">
                        @endif
                    </div>
                    @if(count($media) > 1)
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach($media as $i => $url)
                            @php $isV = str_contains(strtolower($url), '.mp4'); @endphp
                            <img src="{{ $isV ? $url : $url }}" class="media-thumb {{ $i === 0 ? 'active' : '' }}"
                                 onclick="switchMedia('{{ $url }}', {{ $isV ? 'true' : 'false' }}, this)"
                                 style="{{ $isV ? 'background:#000;' : '' }}">
                        @endforeach
                    </div>
                    @endif
                @else
                    <div style="height:240px;background:#f0fdf4;border-radius:12px;display:flex;align-items:center;justify-content:center;color:#6b7280;">
                        <div class="text-center"><i class="bi bi-image" style="font-size:3rem;color:#a7f3d0;"></i><div class="mt-2">Tidak ada foto</div></div>
                    </div>
                @endif
            </div>

            {{-- Title + Price --}}
            <div class="detail-card">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @if($listing->category)<span class="badge-category"><i class="bi bi-tag me-1"></i>{{ $listing->category->name }}</span>@endif
                    <span class="badge-condition">{{ $listing->condition === 'new' ? '✨ Baru' : ($listing->condition === 'used' ? '♻️ Bekas' : '❓ Kondisi N/A') }}</span>
                    @if($listing->location)<span style="font-size:.75rem;color:#6b7280;"><i class="bi bi-geo-alt me-1"></i>{{ $listing->location }}</span>@endif
                </div>
                <h1 style="font-size:1.4rem;font-weight:800;color:#111827;margin-bottom:.6rem;">{{ $listing->title }}</h1>
                <div class="price-tag">{{ $listing->price_formatted }}</div>
                <div style="font-size:.75rem;color:#9ca3af;margin-top:.3rem;">Diposting {{ $listing->source_date?->diffForHumans() ?? $listing->created_at->diffForHumans() }}</div>
            </div>

            {{-- Description --}}
            <div class="detail-card">
                <h5 style="font-weight:700;margin-bottom:1rem;color:#111827;">Deskripsi Produk</h5>
                <div style="color:#374151;line-height:1.9;white-space:pre-wrap;font-size:.9rem;">{{ preg_replace('/\[Analisis Gambar\]:\s*/i', '', $listing->description) ?: 'Tidak ada deskripsi tambahan.' }}</div>
            </div>
        </div>

        {{-- Right: Seller + CTA --}}
        <div class="col-12 col-lg-4">
            {{-- Seller card --}}
            <div class="seller-card mb-3">
                <h6 style="font-weight:700;color:#111827;margin-bottom:1rem;"><i class="bi bi-person-circle me-2" style="color:#059669;"></i>Info Penjual</h6>
                @php
                    $sellerName   = $listing->contact?->name ?? $listing->contact_name ?? 'Penjual';
                    $sellerPhone  = $listing->contact?->phone_number ?? $listing->contact_number;
                    $sellerInitial = mb_strtoupper(mb_substr($sellerName, 0, 1));
                    $sellProducts  = $listing->contact?->sell_products ?? null;
                @endphp
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="seller-avatar">{{ $sellerInitial }}</div>
                    <div>
                        <div style="font-weight:700;color:#111827;">{{ $sellerName }}</div>
                        @if($sellProducts)<div style="font-size:.78rem;color:#6b7280;">{{ Str::limit($sellProducts, 50) }}</div>@endif
                        @if($listing->location)<div style="font-size:.78rem;color:#6b7280;"><i class="bi bi-geo-alt"></i> {{ $listing->location }}</div>@endif
                    </div>
                </div>

                @if($sellerPhone)
                <a href="https://wa.me/{{ preg_replace('/\D/','',$sellerPhone) }}?text={{ urlencode('Halo, saya tertarik dengan ' . $listing->title . ' yang Anda jual di Marketplace Jamaah: ' . url('/p/' . $listing->id)) }}"
                   target="_blank" class="btn-wa w-100 justify-content-center mb-2">
                    <i class="bi bi-whatsapp" style="font-size:1.1rem;"></i> Chat Penjual via WhatsApp
                </a>
                @endif

                @if($listing->contact)
                <a href="{{ route('public.seller', $listing->contact->phone_number) }}" class="btn btn-sm w-100" style="background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;border-radius:10px;font-weight:600;">
                    <i class="bi bi-shop me-1"></i>Lihat Semua Iklan Penjual
                </a>
                @endif
            </div>

            {{-- Share --}}
            <div class="detail-card" style="background:#f8fafc;">
                <h6 style="font-weight:700;color:#111827;margin-bottom:.8rem;"><i class="bi bi-share me-2" style="color:#6366f1;"></i>Bagikan</h6>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="https://wa.me/?text={{ urlencode($listing->title . ' — ' . url('/p/' . $listing->id)) }}" target="_blank"
                       class="btn btn-sm" style="background:#dcfce7;color:#15803d;border:none;border-radius:8px;font-weight:600;">
                        <i class="bi bi-whatsapp"></i> WA
                    </a>
                    <button id="btnNativeShare" onclick="nativeShare()" class="btn btn-sm d-none" style="background:#ede9fe;color:#6d28d9;border:none;border-radius:8px;font-weight:600;">
                        <i class="bi bi-box-arrow-up"></i> Bagikan
                    </button>
                    <button id="btnCopyLink" onclick="copyLink()" class="btn btn-sm" style="background:#f3f4f6;color:#374151;border:none;border-radius:8px;font-weight:600;">
                        <i class="bi bi-link-45deg"></i> Salin Link
                    </button>
                    @if(count($listing->media_urls ?? []))
                    <button id="btnCopyImg" onclick="copyWithImage()" class="btn btn-sm" style="background:#e0f2fe;color:#0369a1;border:none;border-radius:8px;font-weight:600;">
                        <i class="bi bi-image"></i> Salin + Foto
                    </button>
                    @endif
                </div>
            </div>

            {{-- ID Iklan --}}
            <div style="font-size:.75rem;color:#9ca3af;text-align:center;margin-top:.5rem;">
                ID Iklan: #{{ str_pad($listing->id, 5, '0', STR_PAD_LEFT) }}
            </div>
        </div>
    </div>

    {{-- Related --}}
    @if($related->count())
    <div class="mt-5">
        <h4 style="font-weight:800;color:#111827;margin-bottom:1.2rem;">Produk Serupa</h4>
        <div class="row g-3">
            @foreach($related as $rel)
            <div class="col-6 col-md-4 col-lg-2">
                <a href="{{ route('public.listing', $rel->id) }}" class="related-card">
                    @php $relMedia = $rel->media_urls[0] ?? null; @endphp
                    @if($relMedia && preg_match('/\.(mp4|mov|webm)$/i', $relMedia))
                        <video src="{{ $relMedia }}" class="related-img" muted style="object-fit:cover;"></video>
                    @elseif($relMedia)
                        <img src="{{ $relMedia }}" class="related-img" alt="{{ $rel->title }}">
                    @else
                        <div class="related-img d-flex align-items-center justify-content-center" style="background:#ecfdf5;"><i class="bi bi-image" style="color:#a7f3d0;font-size:2rem;"></i></div>
                    @endif
                    <div class="p-2">
                        <div style="font-size:.78rem;font-weight:700;color:#111827;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $rel->title }}</div>
                        <div style="font-size:.8rem;font-weight:800;color:#059669;margin-top:.25rem;">{{ $rel->price_formatted }}</div>
                    </div>
                </a>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
const _pageUrl   = '{{ url('/p/' . $listing->id) }}';
const _pageTitle = @json($listing->title);
const _firstImg  = @json($listing->media_urls[0] ?? null);

// Show native share button if supported
if (navigator.share) document.getElementById('btnNativeShare')?.classList.remove('d-none');

function switchMedia(url, isVideo, el) {
    const wrap = document.getElementById('mainMedia');
    if (isVideo) {
        wrap.outerHTML = `<video id="mainMedia" src="${url}" controls style="width:100%;height:340px;object-fit:cover;border-radius:16px;"></video>`;
    } else {
        if (wrap.tagName === 'VIDEO') {
            wrap.outerHTML = `<img id="mainMedia" src="${url}" style="width:100%;height:340px;object-fit:cover;border-radius:16px;cursor:zoom-in;" onclick="window.open(this.src,'_blank')">`;
        } else {
            wrap.src = url;
        }
    }
    document.querySelectorAll('.media-thumb').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}

async function nativeShare() {
    const shareData = { title: _pageTitle, text: _pageTitle, url: _pageUrl };
    try {
        if (_firstImg && navigator.canShare) {
            try {
                const resp = await fetch(_firstImg);
                const blob = await resp.blob();
                const file = new File([blob], 'foto-iklan.jpg', { type: blob.type });
                if (navigator.canShare({ files: [file] })) {
                    await navigator.share({ ...shareData, files: [file] });
                    return;
                }
            } catch {}
        }
        await navigator.share(shareData);
    } catch (e) {
        if (e.name !== 'AbortError') copyLink();
    }
}

function copyLink() {
    navigator.clipboard.writeText(_pageUrl).then(() => {
        const btn = document.getElementById('btnCopyLink');
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Tersalin!';
        btn.style.background = '#dcfce7'; btn.style.color = '#15803d';
        setTimeout(() => { btn.innerHTML = orig; btn.style.background = '#f3f4f6'; btn.style.color = '#374151'; }, 2000);
    });
}

async function copyWithImage() {
    const btn = document.getElementById('btnCopyImg');
    if (!_firstImg) return;
    try {
        const resp = await fetch(_firstImg);
        const blob = await resp.blob();
        await navigator.clipboard.write([new ClipboardItem({ [blob.type]: blob })]);
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Foto Tersalin!';
        btn.style.background = '#dcfce7'; btn.style.color = '#15803d';
        setTimeout(() => { btn.innerHTML = orig; btn.style.background = '#e0f2fe'; btn.style.color = '#0369a1'; }, 2500);
    } catch (e) {
        // Fallback: copy rich text with image URL
        const text = `${_pageTitle}\n${_pageUrl}\n\nFoto: ${_firstImg}`;
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i> Link+Foto Tersalin!';
            btn.style.background = '#dcfce7'; btn.style.color = '#15803d';
            setTimeout(() => { btn.innerHTML = orig; btn.style.background = '#e0f2fe'; btn.style.color = '#0369a1'; }, 2500);
        });
    }
}
</script>
@endsection
