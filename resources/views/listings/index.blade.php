@extends('layouts.app')
@section('title', 'Listing Iklan')
@section('breadcrumb', 'Listing Iklan')

@section('content')
<div class="page-header">
    <h1><i class="bi bi-grid me-2" style="color:#6366f1;"></i>Listing Iklan</h1>
    <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Semua iklan yang berhasil diekstrak dari percakapan</p>
</div>

<div class="page-body">
    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-grid"></i></div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total Listing</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value">{{ number_format($stats['today']) }}</div>
                <div class="stat-label">Hari Ini</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value">{{ number_format($stats['active']) }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-clock-history"></i></div>
                <div class="stat-value">{{ number_format($stats['pending']) }}</div>
                <div class="stat-label">Pending Review</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-3 px-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari judul, lokasi..." value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-2">
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="sold" {{ request('status') == 'sold' ? 'selected' : '' }}>Terjual</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="group_id" class="form-select form-select-sm">
                        <option value="">Semua Grup</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}" {{ request('group_id') == $g->id ? 'selected' : '' }}>{{ Str::limit($g->group_name, 22) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">Filter</button>
                    <a href="{{ route('listings.index') }}" class="btn btn-sm flex-fill" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Listings Grid -->
    <div class="row g-3">
        @forelse($listings as $listing)
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card h-100" style="transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
                @if($listing->media_urls && count($listing->media_urls))
                <div style="height:160px;background:#f3f4f6;border-radius:12px 12px 0 0;overflow:hidden;">
                    @if(preg_match('/\.(mp4|mov|webm)$/i', $listing->media_urls[0]))
                    <video src="{{ $listing->media_urls[0] }}" style="width:100%;height:100%;object-fit:cover;" muted></video>
                    @else
                    <img src="{{ $listing->media_urls[0] }}" style="width:100%;height:100%;object-fit:cover;">
                    @endif
                </div>
                @endif
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge" style="background:#eef2ff;color:#4f46e5;font-size:.72rem;">{{ $listing->category->name ?? 'Uncategorized' }}</span>
                        @php
                            $sc = ['active'=>['bg'=>'#dcfce7','c'=>'#15803d'],
                                   'sold'=>['bg'=>'#f1f5f9','c'=>'#475569'],
                                   'pending'=>['bg'=>'#fef9c3','c'=>'#92400e'],
                                   'inactive'=>['bg'=>'#fee2e2','c'=>'#b91c1c']];
                            $s = $sc[$listing->status] ?? $sc['inactive'];
                        @endphp
                        <span class="badge" style="background:{{ $s['bg'] }};color:{{ $s['c'] }};font-size:.72rem;">{{ ucfirst($listing->status) }}</span>
                    </div>
                    <div style="font-weight:600;color:#111827;font-size:.9rem;margin-bottom:.25rem;">{{ Str::limit($listing->title, 55) }}</div>
                    <div style="color:#15803d;font-size:1rem;font-weight:700;margin-bottom:.5rem;">{{ $listing->price_formatted }}</div>
                    @if($listing->location)
                    <div style="font-size:.75rem;color:#6b7280;margin-bottom:.5rem;"><i class="bi bi-geo-alt me-1"></i>{{ $listing->location }}</div>
                    @endif
                    @if($listing->description)
                    <p style="font-size:.8rem;color:#374151;line-height:1.5;margin-bottom:.75rem;">{{ Str::limit($listing->description, 80) }}</p>
                    @endif
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:.72rem;color:#9ca3af;">{{ $listing->created_at->diffForHumans() }}</span>
                        <div class="d-flex gap-2">
                            @can('edit listings')
                            <form method="POST" action="{{ route('listings.status', $listing) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <select name="status" onchange="this.form.submit()" class="form-select form-select-sm" style="font-size:.72rem;padding:.15rem .5rem;background:#fff;border:1px solid #d1d5db;color:#374151;width:auto;">
                                    <option value="active" {{ $listing->status == 'active' ? 'selected' : '' }}>Aktif</option>
                                    <option value="sold" {{ $listing->status == 'sold' ? 'selected' : '' }}>Terjual</option>
                                    <option value="pending" {{ $listing->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="inactive" {{ $listing->status == 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                                </select>
                            </form>
                            @endcan
                            <a href="{{ route('listings.show', $listing) }}" class="btn btn-sm" style="background:#eef2ff;border:none;color:#4f46e5;padding:.2rem .6rem;"><i class="bi bi-eye"></i></a>
                            @can('edit listings')
                            <a href="{{ route('listings.edit', $listing) }}" class="btn btn-sm" style="background:#fef3c7;border:none;color:#d97706;padding:.2rem .6rem;"><i class="bi bi-pencil-square"></i></a>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-grid" style="font-size:2.5rem;color:#d1d5db;display:block;margin-bottom:.75rem;"></i>
                    <p style="color:#9ca3af;">Belum ada listing. Listing akan muncul setelah AI memproses pesan iklan.</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>

    @if($listings->hasPages())
    <div class="d-flex justify-content-between align-items-center mt-3">
        <span style="font-size:.8rem;color:#6b7280;">{{ $listings->firstItem() }}–{{ $listings->lastItem() }} dari {{ $listings->total() }}</span>
        {{ $listings->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>
@endsection
