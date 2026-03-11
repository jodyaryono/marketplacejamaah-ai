@extends('layouts.app')
@section('title', 'Kategori')
@section('breadcrumb', 'Kategori')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-tags me-2" style="color:#a78bfa;"></i>Kategori</h1>
        <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Kelola kategori produk iklan</p>
    </div>
    @can('manage categories')
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCatModal"><i class="bi bi-plus-circle me-2"></i>Tambah Kategori</button>
    @endcan
</div>

<div class="page-body">
    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-tags"></i></div>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <div class="stat-label">Total Kategori</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-toggle-on"></i></div>
                <div class="stat-value">{{ $stats['active'] }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-grid"></i></div>
                <div class="stat-value">{{ number_format($stats['total_listings']) }}</div>
                <div class="stat-label">Total Listing</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-star"></i></div>
                <div class="stat-value">{{ $stats['top_category'] ?? '-' }}</div>
                <div class="stat-label">Terbanyak</div>
            </div>
        </div>
    </div>

    <!-- Category Cards Grid -->
    <div class="row g-3">
        @forelse($categories as $cat)
        <div class="col-6 col-md-4 col-xl-3">
            <div class="card h-100" style="transition:transform .2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
                <div class="card-body text-center py-4">
                    <div style="width:52px;height:52px;border-radius:14px;background:{{ $cat->color ? $cat->color.'22' : 'rgba(99,102,241,.15)' }};display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
                        <i class="{{ $cat->icon ?: 'bi-box' }}" style="font-size:1.5rem;color:{{ $cat->color ?: '#818cf8' }};"></i>
                    </div>
                    <div style="font-weight:600;color:#111827;margin-bottom:.25rem;">{{ $cat->name }}</div>
                    <div style="font-size:.8rem;color:#6b7280;margin-bottom:.75rem;">{{ number_format($cat->listing_count) }} listing</div>
                    @if(!$cat->is_active)
                        <span class="badge" style="background:#fee2e2;color:#b91c1c;font-size:.7rem;">Nonaktif</span>
                    @endif
                </div>
                @can('manage categories')
                <div class="card-footer text-center" style="background:transparent;border-top:1px solid #e5e7eb;padding:.5rem;">
                    <button class="btn btn-sm" style="background:transparent;border:none;color:#4f46e5;font-size:.78rem;"
                        onclick="editCat({{ $cat->id }}, '{{ addslashes($cat->name) }}', '{{ $cat->icon }}', '{{ $cat->color }}', {{ $cat->is_active ? 'true' : 'false' }})"
                        data-bs-toggle="modal" data-bs-target="#editCatModal">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </button>
                </div>
                @endcan
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card text-center py-5">
                <div class="card-body">
                    <i class="bi bi-tags" style="font-size:2.5rem;color:#d1d5db;display:block;margin-bottom:.75rem;"></i>
                    <p style="color:#9ca3af;">Belum ada kategori.</p>
                </div>
            </div>
        </div>
        @endforelse
    </div>
</div>

@can('manage categories')
<!-- Add Cat Modal -->
<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('categories.store') }}">
                @csrf
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                    <h5 class="modal-title">Tambah Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nama Kategori</label>
                        <input type="text" name="name" class="form-control" required placeholder="Pakaian">
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" style="color:#374151;font-size:.82rem;">Icon (Bootstrap Icon)</label>
                            <input type="text" name="icon" class="form-control" placeholder="bi-shirt">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="color:#374151;font-size:.82rem;">Warna</label>
                            <input type="color" name="color" class="form-control form-control-color w-100" value="#818cf8">
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Cat Modal -->
<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="editCatForm">
                @csrf @method('PUT')
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                    <h5 class="modal-title">Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nama</label>
                        <input type="text" name="name" id="editCatName" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" style="color:#374151;font-size:.82rem;">Icon</label>
                            <input type="text" name="icon" id="editCatIcon" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label" style="color:#374151;font-size:.82rem;">Warna</label>
                            <input type="color" name="color" id="editCatColor" class="form-control form-control-color w-100">
                        </div>
                    </div>
                    <div class="mt-3 form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editCatActive" value="1">
                        <label class="form-check-label" for="editCatActive" style="color:#374151;font-size:.875rem;">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function editCat(id, name, icon, color, active) {
    document.getElementById('editCatForm').action = '/categories/'+id;
    document.getElementById('editCatName').value = name;
    document.getElementById('editCatIcon').value = icon || '';
    document.getElementById('editCatColor').value = color || '#818cf8';
    document.getElementById('editCatActive').checked = active;
}
</script>
@endcan
@endsection
