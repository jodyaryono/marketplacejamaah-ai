{{-- Reusable listing card partial — used for initial render & AJAX load-more --}}
@php
    if (!function_exists('hed')) { function hed(string $s): string { return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); } }
    $__loc = \App\Support\SiteLocale::get();
    $__t = fn($id, $en) => $__loc === 'en' ? $en : $id;
@endphp
@foreach($listings as $listing)
@php
    $firstMedia = !empty($listing->media_urls) ? $listing->media_urls[0] : null;
    $isVideo = $firstMedia && preg_match('/\.(mp4|mov|webm|avi)$/i', $firstMedia);
    $allMedia = $listing->media_urls ?? [];

    $waPhone = $listing->contact_number ?: $listing->contact?->phone_number;
    $waPhone = preg_replace('/\D/', '', $waPhone ?? '');
    if (str_starts_with($waPhone, '0')) $waPhone = '62' . substr($waPhone, 1);
    elseif ($waPhone && !str_starts_with($waPhone, '62')) $waPhone = '62' . $waPhone;
    if (strlen($waPhone) >= 15 && str_starts_with($waPhone, '2500')) $waPhone = null;
    $waText = urlencode(\App\Support\SiteLocale::waSellerMessage($listing->title));
    $waLink = $waPhone ? 'https://wa.me/' . $waPhone . '?text=' . $waText : null;

    $sellerName = $listing->contact?->name ?: ($listing->contact_name ?: $__t('Penjual','Seller'));
    $sellerLocation = $listing->location ?: $listing->contact?->address;
    $priceDisplay = $listing->price_formatted;
    $priceType    = $listing->price_type ?? 'fix';
    $isNego       = $priceType === 'nego';
    $isLelang     = $priceType === 'lelang';
    $condition    = $listing->condition;
    $mediaJson = json_encode(is_array($listing->media_urls) ? $listing->media_urls : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
@endphp
<div class="col">
    <a href="/p/{{ $listing->id }}" class="product-card text-decoration-none d-block"
       style="cursor:pointer; color:inherit;">

        <div class="product-img-wrap">
            <div style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.52);color:#e5e7eb;font-size:.62rem;font-weight:700;padding:2px 7px;border-radius:20px;z-index:3;letter-spacing:.3px;">#{{ $listing->id }}</div>
            @if($isVideo)
                <video class="card-autoplay-video" preload="none" playsinline muted loop
                       data-src="{{ $firstMedia }}"
                       style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
                </video>
                <div class="video-play-badge"><i class="bi bi-play-circle-fill"></i> {{ $__t('Video','Video') }}</div>
            @elseif($firstMedia)
                <img src="{{ $firstMedia }}" alt="{{ $listing->title }}" loading="lazy"
                     onerror="this.style.display='none';this.parentElement.querySelector('.product-img-placeholder').style.display='flex'">
                <div class="product-img-placeholder" style="display:none"><i class="bi bi-image"></i></div>
            @elseif(!empty($listing->gdrive_url))
                <div class="product-img-placeholder" style="background:#e8f0fe;"><i class="bi bi-play-btn-fill" style="color:#4285f4;font-size:2.5rem;"></i></div>
            @else
                <div class="product-img-placeholder"><i class="bi bi-box-seam"></i></div>
            @endif
            @if(!empty($listing->gdrive_url) && !$isVideo)
                <div class="video-play-badge" style="background:#4285f4;"><i class="bi bi-play-btn-fill me-1"></i>{{ $__t('Video Drive','Drive Video') }}</div>
            @endif
        </div>

        <div class="product-body">
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:.3rem;margin-bottom:.3rem;">
                @if($listing->category)
                    <span class="product-category">{{ \App\Support\SiteLocale::category($listing->category->name) }}</span>
                @endif
                @if($condition === 'new')
                    <span style="font-size:.62rem;font-weight:700;background:#ecfdf5;color:#059669;border:1px solid #a7f3d0;border-radius:20px;padding:1px 7px;">✨ {{ $__t('Baru','New') }}</span>
                @elseif($condition === 'used')
                    <span style="font-size:.62rem;font-weight:700;background:#f3f4f6;color:#6b7280;border:1px solid #d1d5db;border-radius:20px;padding:1px 7px;">♻️ {{ $__t('Bekas','Used') }}</span>
                @endif
                @if($isNego)
                    <span style="font-size:.62rem;font-weight:700;background:#fffbeb;color:#d97706;border:1px solid #fde68a;border-radius:20px;padding:1px 7px;">🤝 {{ $__t('Nego','Negotiable') }}</span>
                @elseif($isLelang)
                    <span style="font-size:.62rem;font-weight:700;background:#fdf4ff;color:#9333ea;border:1px solid #e9d5ff;border-radius:20px;padding:1px 7px;">🔨 {{ $__t('Lelang','Auction') }}</span>
                @endif
            </div>
            <div class="product-title">{{ $listing->title }}</div>
            <div class="product-price {{ $isNego || $isLelang ? 'negotiable' : '' }}">{{ $priceDisplay }}</div>
            <div class="product-seller">
                <i class="bi bi-person-circle"></i>
                <span>{{ $sellerName }}</span>
            </div>
            @if($sellerLocation)
            <div style="font-size:.72rem;color:#6b7280;display:flex;align-items:center;gap:.3rem;padding-top:.15rem;">
                <i class="bi bi-geo-alt-fill" style="color:#059669;font-size:.78rem;"></i>
                <span>{{ \Illuminate\Support\Str::limit($sellerLocation, 28) }}</span>
            </div>
            @endif
            <div style="font-size:.7rem;color:#9ca3af;display:flex;align-items:center;gap:.3rem;padding-top:.2rem;">
                <i class="bi bi-calendar3"></i>
                {{ ($listing->source_date ?? $listing->created_at)?->format('d M Y') }}
            </div>
        </div>

        @if($waLink)
        <div class="product-footer" onclick="event.preventDefault(); event.stopPropagation(); window.open('{{ $waLink }}', '_blank', 'noopener');">
            <span class="btn-wa">
                <i class="bi bi-whatsapp"></i> {{ $__t('Hubungi Penjual','Contact Seller') }}
            </span>
        </div>
        @else
        <div class="product-footer">
            <span class="btn-wa" style="background:linear-gradient(135deg,#374151,#4b5563);">
                <i class="bi bi-eye"></i> {{ $__t('Lihat Detail','View Detail') }}
            </span>
        </div>
        @endif
    </a>
</div>
@endforeach
