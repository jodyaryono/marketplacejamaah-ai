@extends('layouts.app')
@section('title', 'Detail Listing')
@section('breadcrumb', 'Detail Listing')

@section('content')
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('listings.index') }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1><i class="bi bi-tag me-2" style="color:#4ade80;"></i>Detail Listing</h1>
            <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Diperbarui {{ $listing->updated_at->diffForHumans() }}</p>
        </div>
        <div class="ms-auto">
            <a href="{{ route('listings.edit', $listing) }}" class="btn btn-sm" style="background:#4f46e5;color:#fff;border:none;padding:.4rem .9rem;border-radius:8px;font-size:.82rem;"><i class="bi bi-pencil-square me-1"></i>Edit Iklan</a>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="row g-3">
        <!-- Main -->
        <div class="col-12 col-lg-8">
            <!-- Media Gallery -->
            @if($listing->media_urls && count($listing->media_urls))
            <div class="card mb-3">
                <div class="card-body p-2">
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach($listing->media_urls as $url)
                        <a href="{{ $url }}" target="_blank">
                            @if(preg_match('/\.(mp4|mov|webm)$/i', $url))
                            <video src="{{ $url }}" style="height:150px;width:150px;object-fit:cover;border-radius:8px;border:2px solid #e5e7eb;" muted controls></video>
                            @else
                            <img src="{{ $url }}" style="height:150px;width:150px;object-fit:cover;border-radius:8px;border:2px solid #e5e7eb;">
                            @endif
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            @if($listing->gdrive_url)
            @php
                $gdriveEmbed = null;
                if (preg_match('/drive\.google\.com\/file\/d\/([^\/\?]+)/', $listing->gdrive_url, $gm)) {
                    $gdriveEmbed = 'https://drive.google.com/file/d/' . $gm[1] . '/preview';
                } elseif (preg_match('/[?&]id=([^&]+)/', $listing->gdrive_url, $gm)) {
                    $gdriveEmbed = 'https://drive.google.com/file/d/' . $gm[1] . '/preview';
                }
            @endphp
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-play-btn me-2" style="color:#4285f4;"></i>Video Google Drive</span>
                    <a href="{{ $listing->gdrive_url }}" target="_blank" class="float-end" style="font-size:.78rem;color:#4285f4;">Buka di Drive <i class="bi bi-box-arrow-up-right"></i></a>
                </div>
                <div class="card-body p-0">
                    @if($gdriveEmbed)
                    <iframe src="{{ $gdriveEmbed }}" width="100%" height="360" frameborder="0" allowfullscreen style="display:block;border-radius:0 0 8px 8px;"></iframe>
                    @else
                    <div class="p-3"><a href="{{ $listing->gdrive_url }}" target="_blank">{{ $listing->gdrive_url }}</a></div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Description -->
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;">Deskripsi</span>
                </div>
                <div class="card-body">
                    <h3 style="color:#111827;font-size:1.2rem;font-weight:700;margin-bottom:.5rem;">{{ $listing->title }}</h3>
                    <div style="color:#15803d;font-size:1.4rem;font-weight:800;margin-bottom:1rem;">{{ $listing->price_formatted }}</div>
                    <p style="color:#374151;line-height:1.8;white-space:pre-wrap;">{{ $listing->description ?: 'Tidak ada deskripsi.' }}</p>
                </div>
            </div>

            <!-- Source Message -->
            @if($listing->message)
            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-chat-text me-2" style="color:#4f46e5;"></i>Pesan Sumber</span>
                </div>
                <div class="card-body">
                    <div class="p-3 rounded" style="background:#f8fafc;border-left:3px solid #6366f1;">
                        <div style="font-size:.78rem;color:#6b7280;margin-bottom:.4rem;">{{ $listing->message->sender_name }} · {{ $listing->message->created_at->format('d M Y H:i') }}</div>
                        <p style="color:#374151;white-space:pre-wrap;margin:0;font-size:.875rem;">{{ Str::limit($listing->message->raw_body, 300) }}</p>
                    </div>
                    <a href="{{ route('messages.show', $listing->message) }}" class="btn btn-sm mt-2" style="background:#eef2ff;border:none;color:#4f46e5;">Lihat Pesan Lengkap</a>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-lg-4">
            <!-- Details -->
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;">Informasi Produk</span>
                </div>
                <div class="card-body">
                    <table class="table mb-0" style="font-size:.82rem;">
                        <tbody>
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;border-top:0;">Status</td>
                                <td class="text-end" style="padding:.5rem 0;border-top:0;">
                                    @php $sc=['active'=>['bg'=>'#dcfce7','c'=>'#15803d'],'sold'=>['bg'=>'#f3f4f6','c'=>'#6b7280'],'pending'=>['bg'=>'#fef9c3','c'=>'#92400e'],'inactive'=>['bg'=>'#fee2e2','c'=>'#b91c1c']]; $s=$sc[$listing->status]??$sc['inactive']; @endphp
                                    <span class="badge" style="background:{{ $s['bg'] }};color:{{ $s['c'] }};">{{ ucfirst($listing->status) }}</span>
                                </td>
                            </tr>
                            @if($listing->category)
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Kategori</td>
                                <td class="text-end" style="padding:.5rem 0;color:#4f46e5;">{{ $listing->category->name }}</td>
                            </tr>
                            @endif
                            @if($listing->condition)
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Kondisi</td>
                                <td class="text-end" style="padding:.5rem 0;color:#cbd5e1;">{{ ucfirst($listing->condition) }}</td>
                            </tr>
                            @endif
                            @if($listing->location)
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Lokasi</td>
                                <td class="text-end" style="padding:.5rem 0;color:#cbd5e1;">{{ $listing->location }}</td>
                            </tr>
                            @endif
                            @if($listing->seller_name)
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Penjual</td>
                                <td class="text-end" style="padding:.5rem 0;color:#cbd5e1;">{{ $listing->seller_name }}</td>
                            </tr>
                            @endif
                            @if($listing->seller_phone)
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Kontak</td>
                                <td class="text-end" style="padding:.5rem 0;">
                                    <a href="https://wa.me/{{ $listing->seller_phone }}" target="_blank" style="color:#15803d;text-decoration:none;"><i class="bi bi-whatsapp me-1"></i>{{ $listing->seller_phone }}</a>
                                </td>
                            </tr>
                            @endif
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Dibuat</td>
                                <td class="text-end" style="padding:.5rem 0;color:#6b7280;font-size:.78rem;">{{ $listing->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Status Update -->
            @can('edit listings')
            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;">Ubah Status</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('listings.status', $listing) }}">
                        @csrf @method('PATCH')
                        <select name="status" class="form-select form-select-sm mb-2">
                            <option value="active" {{ $listing->status == 'active' ? 'selected' : '' }}>Aktif</option>
                            <option value="sold" {{ $listing->status == 'sold' ? 'selected' : '' }}>Terjual</option>
                            <option value="pending" {{ $listing->status == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="inactive" {{ $listing->status == 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100">Simpan</button>
                    </form>
                </div>
            </div>
            @endcan
        </div>
    </div>
</div>
@endsection
