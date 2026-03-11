{{-- Reusable listing card partial — used for initial render & AJAX load-more --}}
@php if (!function_exists('hed')) { function hed(string $s): string { return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); } } @endphp
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
    $waText = urlencode('Halo, saya tertarik dengan produk "' . $listing->title . '". Apakah masih tersedia?');
    $waLink = $waPhone ? 'https://wa.me/' . $waPhone . '?text=' . $waText : null;

    $sellerName = $listing->contact?->name ?: ($listing->contact_name ?: 'Penjual');
    if ($listing->price_label) $priceDisplay = $listing->price_label;
    elseif ($listing->price_min && $listing->price_max) $priceDisplay = 'Rp ' . number_format($listing->price_min,0,',','.') . ' – ' . number_format($listing->price_max,0,',','.');
    elseif ($listing->price && $listing->price > 0) $priceDisplay = 'Rp ' . number_format($listing->price,0,',','.');
    else $priceDisplay = 'Harga nego';
    $mediaJson = json_encode(is_array($listing->media_urls) ? $listing->media_urls : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_APOS);
@endphp
<div class="col">
    <a href="/p/{{ $listing->id }}" class="product-card text-decoration-none d-block"
       style="cursor:pointer; color:inherit;">

        <div class="product-img-wrap">
            @if($isVideo)
                <video class="card-autoplay-video" preload="none" playsinline muted loop
                       data-src="{{ $firstMedia }}"
                       style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
                </video>
                <div class="video-play-badge"><i class="bi bi-play-circle-fill"></i> Video</div>
            @elseif($firstMedia)
                <img src="{{ $firstMedia }}" alt="{{ $listing->title }}" loading="lazy"
                     onerror="this.style.display='none';this.parentElement.querySelector('.product-img-placeholder').style.display='flex'">
                <div class="product-img-placeholder" style="display:none"><i class="bi bi-image"></i></div>
            @else
                <div class="product-img-placeholder"><i class="bi bi-box-seam"></i></div>
            @endif
        </div>

        <div class="product-body">
            @if($listing->category)
                <span class="product-category">{{ $listing->category->name }}</span>
            @endif
            <div class="product-title">{{ $listing->title }}</div>
            @if($listing->price_label)
                <div class="product-price negotiable">{{ $listing->price_label }}</div>
            @elseif($listing->price_min && $listing->price_max)
                <div class="product-price negotiable">Rp {{ number_format($listing->price_min,0,',','.') }} – {{ number_format($listing->price_max,0,',','.') }}</div>
            @elseif($listing->price && $listing->price > 0)
                <div class="product-price">Rp {{ number_format($listing->price,0,',','.') }}</div>
            @else
                <div class="product-price negotiable">Harga nego</div>
            @endif
            <div class="product-seller">
                <i class="bi bi-person-circle"></i>
                <span>{{ $sellerName }}</span>
                @if($listing->location)
                    <span class="ms-auto d-flex align-items-center gap-1">
                        <i class="bi bi-geo-alt"></i>{{ \Illuminate\Support\Str::limit($listing->location, 14) }}
                    </span>
                @endif
            </div>
            <div style="font-size:.7rem;color:#9ca3af;display:flex;align-items:center;gap:.3rem;padding-top:.2rem;">
                <i class="bi bi-calendar3"></i>
                {{ ($listing->source_date ?? $listing->created_at)?->format('d M Y') }}
            </div>
        </div>

        @if($waLink)
        <div class="product-footer" onclick="event.preventDefault(); event.stopPropagation(); window.open('{{ $waLink }}', '_blank', 'noopener');">
            <span class="btn-wa">
                <i class="bi bi-whatsapp"></i> Hubungi Penjual
            </span>
        </div>
        @else
        <div class="product-footer">
            <span class="btn-wa" style="background:linear-gradient(135deg,#374151,#4b5563);">
                <i class="bi bi-eye"></i> Lihat Detail
            </span>
        </div>
        @endif
    </a>
</div>
@endforeach
