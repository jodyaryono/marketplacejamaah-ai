@extends('layouts.app')
@section('title', 'Edit Listing')
@section('breadcrumb', 'Edit Listing')

@section('content')
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('listings.show', $listing) }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1><i class="bi bi-pencil-square me-2" style="color:#4ade80;"></i>Edit Listing</h1>
            <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">{{ $listing->title }}</p>
        </div>
    </div>
</div>

<div class="page-body">
    @if(session('success'))
    <div class="alert alert-success mb-3" style="background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;border-radius:10px;padding:.75rem 1rem;">
        <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="alert mb-3" style="background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;border-radius:10px;padding:.75rem 1rem;">
        <i class="bi bi-exclamation-circle me-2"></i>{{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('listings.update', $listing) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="row g-3">
            <!-- Left column -->
            <div class="col-12 col-lg-8">
                <!-- Media management -->
                <div class="card mb-3">
                    <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                        <span style="font-weight:600;color:#111827;font-size:.9rem;">Media Produk</span>
                    </div>
                    <div class="card-body">
                        @if($listing->media_urls && count($listing->media_urls))
                        <div class="d-flex gap-2 flex-wrap mb-3" id="existingPhotos">
                            @foreach($listing->media_urls as $url)
                            <div class="position-relative" style="width:110px;">
                                @if(preg_match('/\.(mp4|mov|webm)$/i', $url))
                                <video src="{{ $url }}" style="height:110px;width:110px;object-fit:cover;border-radius:8px;border:2px solid #e5e7eb;display:block;" muted></video>
                                @else
                                <img src="{{ $url }}" style="height:110px;width:110px;object-fit:cover;border-radius:8px;border:2px solid #e5e7eb;display:block;">
                                @endif
                                <button type="button" onclick="removePhoto(this,'{{ addslashes($url) }}')" class="position-absolute top-0 end-0 btn btn-sm" style="background:#ef4444;color:#fff;border:none;border-radius:50%;width:22px;height:22px;padding:0;line-height:1;font-size:.7rem;margin:3px;">&times;</button>
                                <input type="hidden" name="keep_media[]" value="{{ $url }}" class="keep-input">
                            </div>
                            @endforeach
                        </div>
                        @endif
                        <div>
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Tambah Foto / Video Baru</label>
                            <div id="pasteDropZone"
                                 onclick="document.getElementById('newPhotosInput').click()"
                                 style="border:2px dashed #d1d5db;border-radius:8px;padding:1.2rem 1rem;text-align:center;cursor:pointer;background:#f9fafb;transition:border-color .15s,background .15s;">
                                <i class="bi bi-images" style="font-size:1.6rem;color:#9ca3af;"></i>
                                <div style="font-size:.82rem;color:#4b5563;margin-top:.35rem;font-weight:500;">Klik pilih file <span style="color:#9ca3af;">atau tempel gambar (Ctrl+V)</span></div>
                                <div style="font-size:.72rem;color:#9ca3af;margin-top:.2rem;">Maks 5 file · foto maks 5MB · video maks 20MB · JPG, PNG, WEBP, MP4, WEBM</div>
                            </div>
                            <input type="file" id="newPhotosInput" name="new_photos[]" multiple accept="image/*,video/mp4,video/webm,video/quicktime" style="display:none;">
                            <div id="newPhotoPreview" class="d-flex gap-2 flex-wrap mt-2"></div>
                        </div>
                    </div>
                </div>

                <!-- Main fields -->
                <div class="card mb-3">
                    <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                        <span style="font-weight:600;color:#111827;font-size:.9rem;">Informasi Iklan</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Judul *</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $listing->title) }}" required style="font-size:.875rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Deskripsi</label>
                            <textarea name="description" class="form-control" rows="7" style="font-size:.875rem;line-height:1.7;">{{ old('description', $listing->description) }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Harga (angka)</label>
                                <input type="number" name="price" class="form-control" value="{{ old('price', $listing->price) }}" min="0" step="1000" placeholder="contoh: 150000" style="font-size:.875rem;">
                                <div class="form-text" style="font-size:.75rem;">Kosongkan jika pakai label harga.</div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Label Harga</label>
                                <input type="text" name="price_label" class="form-control" value="{{ old('price_label', $listing->price_label) }}" placeholder="contoh: Harga nego, 50k & 100k" style="font-size:.875rem;">
                                <div class="form-text" style="font-size:.75rem;">Dipakai jika harga tidak berupa angka tunggal.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right column -->
            <div class="col-12 col-lg-4">
                <div class="card mb-3">
                    <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                        <span style="font-weight:600;color:#111827;font-size:.9rem;">Detail Produk</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Kategori</label>
                            <select name="category_id" class="form-select" style="font-size:.875rem;">
                                <option value="">— Pilih Kategori —</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id', $listing->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Kondisi</label>
                            <select name="condition" class="form-select" style="font-size:.875rem;">
                                <option value="new"     {{ old('condition', $listing->condition) == 'new'     ? 'selected' : '' }}>Baru</option>
                                <option value="used"    {{ old('condition', $listing->condition) == 'used'    ? 'selected' : '' }}>Bekas</option>
                                <option value="unknown" {{ old('condition', $listing->condition) == 'unknown' ? 'selected' : '' }}>Tidak diketahui</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Lokasi</label>
                            <input type="text" name="location" class="form-control" value="{{ old('location', $listing->location) }}" placeholder="contoh: Jakarta Selatan" style="font-size:.875rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">No. Kontak Penjual</label>
                            <input type="text" name="contact_number" class="form-control" value="{{ old('contact_number', $listing->contact_number) }}" placeholder="contoh: 0812xxxx" style="font-size:.875rem;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">Status</label>
                            <select name="status" class="form-select" style="font-size:.875rem;">
                                <option value="active"   {{ old('status', $listing->status) == 'active'   ? 'selected' : '' }}>Aktif</option>
                                <option value="pending"  {{ old('status', $listing->status) == 'pending'  ? 'selected' : '' }}>Pending</option>
                                <option value="sold"     {{ old('status', $listing->status) == 'sold'     ? 'selected' : '' }}>Terjual</option>
                                <option value="expired"  {{ old('status', $listing->status) == 'expired'  ? 'selected' : '' }}>Kadaluarsa</option>
                                <option value="inactive" {{ old('status', $listing->status) == 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary" style="background:#059669;border:none;padding:.6rem;font-weight:600;border-radius:10px;">
                        <i class="bi bi-check-circle me-2"></i>Simpan Perubahan
                    </button>
                    <a href="{{ route('listings.show', $listing) }}" class="btn btn-secondary" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;padding:.6rem;border-radius:10px;">
                        Batal
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>
@push('scripts')
<script>
// ── Existing photo remove/restore ──────────────────────────────────────────
function removePhoto(btn, url) {
    const wrapper = btn.closest('.position-relative');
    wrapper.querySelector('.keep-input').disabled = true;
    wrapper.style.opacity = '0.3';
    btn.textContent = '\u21ba';
    btn.onclick = function() { restorePhoto(btn, wrapper); };
}
function restorePhoto(btn, wrapper) {
    wrapper.querySelector('.keep-input').disabled = false;
    wrapper.style.opacity = '1';
    btn.textContent = '\u00d7';
    btn.onclick = function() { removePhoto(btn, ''); };
}

// ── New photo paste/select with previews ───────────────────────────────────
(function () {
    let pending = []; // Array<File>
    const input    = document.getElementById('newPhotosInput');
    const preview  = document.getElementById('newPhotoPreview');
    const dropZone = document.getElementById('pasteDropZone');

    // Sync pending[] → input.files
    function syncToInput() {
        const dt = new DataTransfer();
        pending.forEach(f => dt.items.add(f));
        input.files = dt.files;
    }

    // Render all previews from scratch
    function renderPreviews() {
        preview.innerHTML = '';
        pending.forEach((file, idx) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'position-relative';
            wrapper.style.cssText = 'width:110px;';

            let mediaEl;
            if (file.type.startsWith('video/')) {
                mediaEl = document.createElement('video');
                mediaEl.style.cssText = 'height:110px;width:110px;object-fit:cover;border-radius:8px;border:2px solid #a3e635;display:block;';
                mediaEl.muted = true;
                mediaEl.src = URL.createObjectURL(file);
            } else {
                mediaEl = document.createElement('img');
                mediaEl.style.cssText = 'height:110px;width:110px;object-fit:cover;border-radius:8px;border:2px solid #a3e635;display:block;';
                const reader = new FileReader();
                reader.onload = e => { mediaEl.src = e.target.result; };
                reader.readAsDataURL(file);
            }

            const badge = document.createElement('div');
            badge.style.cssText = 'position:absolute;bottom:3px;left:3px;background:rgba(0,0,0,.5);color:#fff;font-size:.6rem;padding:1px 4px;border-radius:4px;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
            badge.textContent = file.name || 'clipboard';

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = '\u00d7';
            removeBtn.setAttribute('style', 'position:absolute;top:3px;right:3px;background:#ef4444;color:#fff;border:none;border-radius:50%;width:22px;height:22px;padding:0;line-height:1;font-size:.7rem;cursor:pointer;');
            removeBtn.onclick = function () {
                pending.splice(idx, 1);
                syncToInput();
                renderPreviews();
            };

            wrapper.appendChild(mediaEl);
            wrapper.appendChild(badge);
            wrapper.appendChild(removeBtn);
            preview.appendChild(wrapper);
        });

        // Visual feedback on drop zone
        if (pending.length > 0) {
            dropZone.style.borderColor = '#4ade80';
            dropZone.style.background  = '#f0fdf4';
        } else {
            dropZone.style.borderColor = '#d1d5db';
            dropZone.style.background  = '#f9fafb';
        }
    }

    // File input change (manual browse)
    let _syncing = false;
    input.addEventListener('change', function () {
        if (_syncing) return;
        Array.from(this.files).forEach(f => {
            if (!pending.some(p => p.name === f.name && p.size === f.size)) {
                pending.push(f);
            }
        });
        syncToInput();
        renderPreviews();
    });

    // Paste anywhere on page
    document.addEventListener('paste', function (e) {
        const items = e.clipboardData?.items;
        if (!items) return;
        let added = false;
        for (const item of items) {
            if (item.type.startsWith('image/') || item.type.startsWith('video/')) {
                const file = item.getAsFile();
                if (file) { pending.push(file); added = true; }
            }
        }
        if (added) {
            _syncing = true;
            syncToInput();
            _syncing = false;
            renderPreviews();
            e.preventDefault();
        }
    });

    // Drag-and-drop onto zone
    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.style.borderColor = '#4ade80';
        this.style.background  = '#f0fdf4';
    });
    dropZone.addEventListener('dragleave', function () {
        if (pending.length === 0) {
            this.style.borderColor = '#d1d5db';
            this.style.background  = '#f9fafb';
        }
    });
    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        Array.from(e.dataTransfer.files).forEach(f => {
            if (f.type.startsWith('image/') || f.type.startsWith('video/')) pending.push(f);
        });
        _syncing = true;
        syncToInput();
        _syncing = false;
        renderPreviews();
    });
}());
</script>
@endpush
@endsection
