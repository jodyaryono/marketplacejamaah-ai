{{-- Iklan Baris partial — teks tanpa foto, dipakai untuk initial render & AJAX load-more --}}
@foreach($listings as $listing)
@php
    $normalize = function ($raw) {
        $digits = preg_replace('/\D/', '', (string) ($raw ?? ''));
        if (!$digits) return null;
        if (str_starts_with($digits, '0'))       $digits = '62' . substr($digits, 1);
        elseif (str_starts_with($digits, '8'))   $digits = '62' . $digits;
        elseif (!str_starts_with($digits, '62')) return null;
        return preg_match('/^62\d{8,13}$/', $digits) ? $digits : null;
    };
    $waPhone = $normalize($listing->contact_number) ?: $normalize($listing->contact?->phone_number);
    $waText  = urlencode('Halo, saya tertarik dengan "' . $listing->title . '". Apakah masih tersedia?');
    $waLink  = $waPhone ? 'https://wa.me/' . $waPhone . '?text=' . $waText : null;
    $sellerName = $listing->contact?->name ?: ($listing->contact_name ?: 'Penjual');
    $sellerLocation = $listing->location ?: $listing->contact?->address;

    if ($listing->price_label)                                   $priceDisplay = \Illuminate\Support\Str::limit(trim($listing->price_label), 22);
    elseif ($listing->price_min && $listing->price_max)          $priceDisplay = 'Rp ' . number_format($listing->price_min,0,',','.') . '–' . number_format($listing->price_max,0,',','.');
    elseif ($listing->price && $listing->price > 0)              $priceDisplay = 'Rp ' . number_format($listing->price,0,',','.');
    else                                                         $priceDisplay = 'Harga Hubungi Penjual';
@endphp
<div class="iklan-baris-row" onclick="window.location='/p/{{ $listing->id }}'" style="cursor:pointer;">
    <div class="iklan-baris-main">
        @if($listing->category)
            <span class="iklan-baris-cat">{{ $listing->category->name }}</span>
        @endif
        <div class="iklan-baris-title">{{ $listing->title }}</div>
        @if($listing->description)
            <div class="iklan-baris-desc">{{ \Illuminate\Support\Str::limit(strip_tags($listing->description), 110) }}</div>
        @endif
        <div class="iklan-baris-meta">
            <span style="color:#d1d5db;font-weight:600;">#{{ $listing->id }}</span>&nbsp;&middot;
            <i class="bi bi-person-circle"></i>{{ \Illuminate\Support\Str::limit($sellerName, 14) }}
            @if($sellerLocation)
                &nbsp;<i class="bi bi-geo-alt-fill" style="color:#059669;"></i>{{ \Illuminate\Support\Str::limit($sellerLocation, 18) }}
            @endif
            &nbsp;<i class="bi bi-calendar3"></i>{{ ($listing->source_date ?? $listing->created_at)?->format('d M') }}
        </div>
    </div>
    <span class="iklan-baris-price">{{ $priceDisplay }}</span>
    @if($waLink)
        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="iklan-baris-wa"
           onclick="event.stopPropagation();">
            <i class="bi bi-whatsapp"></i><span class="d-none d-sm-inline">Hubungi</span><span class="d-inline d-sm-none">WA</span>
        </a>
    @else
        <a href="/p/{{ $listing->id }}" class="iklan-baris-wa iklan-baris-wa--detail"
           onclick="event.stopPropagation();">
            <i class="bi bi-box-arrow-up-right"></i><span class="d-none d-sm-inline">Detail</span><span class="d-inline d-sm-none">Detail</span>
        </a>
    @endif
</div>
@endforeach
