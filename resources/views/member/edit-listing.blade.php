@extends('layouts.public')
@section('title', 'Edit Iklan — Marketplace Jamaah')

@section('styles')
<style>
    .edit-hero { background:linear-gradient(135deg,#022c22,#064e3b); padding:1.75rem 0; color:#fff; }
    .form-card { background:#fff; border:1px solid #d1fae5; border-radius:16px; padding:1.75rem; }
    .form-label { font-size:.82rem; font-weight:700; color:#374151; margin-bottom:.3rem; }
    .form-control, .form-select { border:1.5px solid #d1fae5; border-radius:10px; font-size:.9rem; padding:.6rem .9rem; transition:border-color .15s; }
    .form-control:focus, .form-select:focus { border-color:#059669; box-shadow:0 0 0 3px rgba(5,150,105,.1); outline:none; }
    .btn-save { background:linear-gradient(135deg,#059669,#047857); color:#fff; border:none; border-radius:10px; font-weight:700; padding:.65rem 1.75rem; font-size:.95rem; transition:all .2s; }
    .btn-save:hover { background:linear-gradient(135deg,#047857,#065f46); color:#fff; transform:translateY(-1px); }
    .btn-cancel { background:#f9fafb; color:#374151; border:1.5px solid #e5e7eb; border-radius:10px; font-weight:600; padding:.65rem 1.25rem; font-size:.9rem; }
    .btn-cancel:hover { background:#f3f4f6; color:#111827; }
    .section-title { font-size:.72rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#059669; margin-bottom:1rem; padding-bottom:.4rem; border-bottom:2px solid #ecfdf5; }
    .price-toggle-btn { border-radius:8px; font-size:.8rem; font-weight:600; padding:.35rem .85rem; border:1.5px solid #d1fae5; background:#f9fafb; color:#374151; cursor:pointer; transition:all .15s; }
    .price-toggle-btn.active { background:#ecfdf5; border-color:#059669; color:#059669; }
    .no-edit-note { background:#fef9ec; border:1px solid #fde68a; border-radius:10px; padding:.75rem 1rem; font-size:.8rem; color:#92400e; }
    .img-thumb { width:80px; height:80px; object-fit:cover; border-radius:8px; border:2px solid #d1fae5; }
</style>
@endsection

@section('content')
<div class="edit-hero">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('member.dashboard') }}" style="color:rgba(255,255,255,.6);text-decoration:none;font-size:1.3rem;"><i class="bi bi-arrow-left"></i></a>
            <div>
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:.15rem;">Edit Iklan</div>
                <h1 style="font-size:1.2rem;font-weight:800;color:#fff;margin:0;">{{ Str::limit($listing->title, 50) }}</h1>
            </div>
        </div>
    </div>
</div>

<div class="container py-4" style="max-width:700px;">

    @if($errors->any())
        <div class="alert alert-danger rounded-3 mb-3" style="font-size:.85rem;">
            <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Note: no create, edit only --}}
    <div class="no-edit-note mb-4">
        <i class="bi bi-info-circle-fill me-1"></i>
        <strong>Perlu iklan baru?</strong> Post di grup WhatsApp — AI akan membuat listing otomatis. Di sini hanya untuk mengedit iklan yang sudah ada.
    </div>

    <form method="POST" action="{{ route('member.listing.update', $listing->id) }}" enctype="multipart/form-data">
        @csrf

        {{-- Media section --}}
        <div class="form-card mb-3">
            <div class="section-title"><i class="bi bi-images me-1"></i>Media Iklan</div>

            @if(!empty($listing->media_urls))
            <div class="d-flex gap-2 flex-wrap mb-3" id="existing-photos">
                @foreach($listing->media_urls as $url)
                <div class="existing-photo-wrap" id="photo-{{ md5($url) }}" style="position:relative;">
                    @if(preg_match('/\.(mp4|mov|webm)$/i', $url))
                        <video src="{{ $url }}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #d1fae5;"></video>
                    @else
                        <img src="{{ $url }}" class="img-thumb" alt="foto">
                    @endif
                    <button type="button"
                            onclick="removeExistingPhoto('{{ addslashes($url) }}', '{{ md5($url) }}')"
                            title="Hapus foto ini"
                            style="position:absolute;top:-7px;right:-7px;width:22px;height:22px;border-radius:50%;background:#ef4444;color:#fff;border:none;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;line-height:1;font-weight:700;">
                        &times;
                    </button>
                </div>
                @endforeach
            </div>
            @endif

            <div id="remove-inputs"></div>

            <label class="form-label mt-1"><i class="bi bi-plus-circle me-1"></i>Tambah Foto / Video Baru</label>
            <input type="file" name="new_photos[]" id="new-photos-input" class="form-control"
                   accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,video/quicktime" multiple>
            <div style="font-size:.72rem;color:#9ca3af;margin-top:.3rem;">Maks 5 file. Foto maks 5 MB, video maks 20 MB. Format: JPG, PNG, WEBP, MP4, WEBM</div>
            <div class="d-flex gap-2 flex-wrap mt-2" id="new-photos-preview"></div>
        </div>

        {{-- Basic info --}}
        <div class="form-card mb-3">
            <div class="section-title"><i class="bi bi-tag me-1"></i>Informasi Iklan</div>

            <div class="mb-3">
                <label class="form-label">Judul Iklan <span style="color:#dc2626;">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title', $listing->title) }}" required maxlength="255" placeholder="Judul singkat dan jelas">
            </div>

            <div class="mb-3">
                <label class="form-label">Deskripsi</label>
                <textarea name="description" class="form-control" rows="5" maxlength="3000" placeholder="Detail produk / layanan Anda...">{{ old('description', $listing->description) }}</textarea>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Kategori</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Pilih Kategori —</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ (old('category_id', $listing->category_id) == $cat->id) ? 'selected' : '' }}>
                                {{ $cat->icon ? $cat->icon . ' ' : '' }}{{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kondisi <span style="color:#dc2626;">*</span></label>
                    <select name="condition" class="form-select" required>
                        <option value="new"     {{ old('condition', $listing->condition) === 'new'     ? 'selected' : '' }}>Baru</option>
                        <option value="used"    {{ old('condition', $listing->condition) === 'used'    ? 'selected' : '' }}>Bekas</option>
                        <option value="unknown" {{ old('condition', $listing->condition) === 'unknown' ? 'selected' : '' }}>Tidak Disebutkan</option>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Lokasi</label>
                <input type="text" name="location" class="form-control" value="{{ old('location', $listing->location) }}" maxlength="255" placeholder="Kota / wilayah">
            </div>
        </div>

        {{-- Harga --}}
        <div class="form-card mb-3">
            <div class="section-title"><i class="bi bi-cash-coin me-1"></i>Harga</div>
            <div class="d-flex gap-2 mb-3" id="price-tabs">
                <button type="button" class="price-toggle-btn {{ old('price_label', $listing->price_label) ? '' : 'active' }}" onclick="switchPrice('numeric')">Harga (Rp)</button>
                <button type="button" class="price-toggle-btn {{ old('price_label', $listing->price_label) ? 'active' : '' }}" onclick="switchPrice('label')">Teks bebas</button>
            </div>
            <div id="price-numeric" style="{{ old('price_label', $listing->price_label) ? 'display:none' : '' }}">
                <input type="number" name="price" class="form-control" value="{{ old('price', $listing->price) }}" min="0" step="1000" placeholder="contoh: 150000">
                <div style="font-size:.72rem;color:#9ca3af;margin-top:.3rem;">Kosongkan jika harga tidak ingin dicantumkan</div>
            </div>
            <div id="price-label" style="{{ old('price_label', $listing->price_label) ? '' : 'display:none' }}">
                <input type="text" name="price_label" class="form-control" value="{{ old('price_label', $listing->price_label) }}" maxlength="100" placeholder="misal: Negotiable, Hubungi penjual, Gratis">
            </div>
        </div>

        {{-- Status --}}
        <div class="form-card mb-4">
            <div class="section-title"><i class="bi bi-toggle-on me-1"></i>Status Iklan</div>
            <div class="d-flex gap-3">
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:.9rem;font-weight:600;">
                    <input type="radio" name="status" value="active" {{ old('status', $listing->status) === 'active' ? 'checked' : '' }} style="accent-color:#059669;">
                    <span style="color:#15803d;"><i class="bi bi-check-circle-fill me-1 text-success"></i>Aktif</span>
                </label>
                <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-size:.9rem;font-weight:600;">
                    <input type="radio" name="status" value="sold" {{ old('status', $listing->status) === 'sold' ? 'checked' : '' }} style="accent-color:#6b7280;">
                    <span style="color:#6b7280;"><i class="bi bi-x-circle-fill me-1"></i>Terjual</span>
                </label>
            </div>
        </div>

        <div class="d-flex gap-3">
            <button type="submit" class="btn-save"><i class="bi bi-check-lg me-1"></i>Simpan Perubahan</button>
            <a href="{{ route('member.dashboard') }}" class="btn-cancel text-decoration-none d-inline-flex align-items-center">Batal</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
function switchPrice(mode) {
    document.getElementById('price-numeric').style.display = mode === 'numeric' ? '' : 'none';
    document.getElementById('price-label').style.display   = mode === 'label'   ? '' : 'none';
    document.querySelectorAll('.price-toggle-btn').forEach((btn, i) => {
        btn.classList.toggle('active', (mode === 'numeric' && i === 0) || (mode === 'label' && i === 1));
    });
    if (mode === 'numeric') document.querySelector('[name=price_label]').value = '';
    if (mode === 'label')   document.querySelector('[name=price]').value = '';
}

function removeExistingPhoto(url, hash) {
    var wrap = document.getElementById('photo-' + hash);
    if (wrap) wrap.remove();
    var inp = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'remove_media[]';
    inp.value = url;
    document.getElementById('remove-inputs').appendChild(inp);
}

document.getElementById('new-photos-input').addEventListener('change', function() {
    var preview = document.getElementById('new-photos-preview');
    preview.innerHTML = '';
    Array.from(this.files).forEach(function(file) {
        if (file.type.startsWith('video/')) {
            var vid = document.createElement('video');
            vid.src = URL.createObjectURL(file);
            vid.muted = true;
            vid.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #a7f3d0;';
            preview.appendChild(vid);
        } else {
            var reader = new FileReader();
            reader.onload = function(e) {
                var img = document.createElement('img');
                img.src = e.target.result;
                img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid #a7f3d0;';
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
@endsection
