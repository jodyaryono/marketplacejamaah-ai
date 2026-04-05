{{-- Iklan Baris partial — teks tanpa foto, dipakai untuk initial render & AJAX load-more --}}
@foreach($listings as $listing)
@php
    $waPhone = $listing->contact_number ?: $listing->contact?->phone_number;
    $waPhone = preg_replace('/\D/', '', $waPhone ?? '');
    if (str_starts_with($waPhone, '0')) $waPhone = '62' . substr($waPhone, 1);
    elseif ($waPhone && !str_starts_with($waPhone, '62')) $waPhone = '62' . $waPhone;
    if (strlen($waPhone) >= 15 && str_starts_with($waPhone, '2500')) $waPhone = null;
    $waText  = urlencode('Halo, saya tertarik dengan "' . $listing->title . '". Apakah masih tersedia?');
    $waLink  = $waPhone ? 'https://wa.me/' . $waPhone . '?text=' . $waText : null;
    $sellerName = $listing->contact?->name ?: ($listing->contact_name ?: 'Penjual');
    $sellerLocation = $listing->location ?: $listing->contact?->address;

    if ($listing->price_label)                                   $priceDisplay = $listing->price_label;
    elseif ($listing->price_min && $listing->price_max)          $priceDisplay = 'Rp ' . number_format($listing->price_min,0,',','.') . '–' . number_format($listing->price_max,0,',','.');
    elseif ($listing->price && $listing->price > 0)              $priceDisplay = 'Rp ' . number_format($listing->price,0,',','.');
    else                                                         $priceDisplay = 'Harga Hubungi Penjual';
@endphp
<div class="iklan-baris-row" onclick="window.location='/p/{{ $listing->id }}'" style="cursor:pointer;">
    @if($listing->category)
        <span class="iklan-baris-cat">{{ $listing->category->name }}</span>
    @endif
    <div class="iklan-baris-main">
        <div class="iklan-baris-title">{{ $listing->title }}</div>
        @if($listing->description)
            <div class="iklan-baris-desc">{{ \Illuminate\Support\Str::limit(strip_tags($listing->description), 90) }}</div>
        @endif
    </div>
    <span class="iklan-baris-price">{{ $priceDisplay }}</span>
    <span class="iklan-baris-meta">
        <span style="color:#d1d5db;font-weight:600;">#{{ $listing->id }}</span>&nbsp;&middot;
        <i class="bi bi-person-circle"></i>{{ \Illuminate\Support\Str::limit($sellerName, 14) }}
        @if($sellerLocation)
            &nbsp;<i class="bi bi-geo-alt-fill" style="color:#059669;"></i>{{ \Illuminate\Support\Str::limit($sellerLocation, 18) }}
        @endif
        &nbsp;<i class="bi bi-calendar3"></i>{{ ($listing->source_date ?? $listing->created_at)?->format('d M') }}
    </span>
    @if($waLink)
        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="iklan-baris-wa"
           onclick="event.stopPropagation();">
            <i class="bi bi-whatsapp"></i><span class="d-none d-sm-inline">Hubungi</span><span class="d-inline d-sm-none">WA</span>
        </a>
    @endif
</div>
@endforeach
