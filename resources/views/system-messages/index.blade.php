@extends('layouts.app')

@section('title', 'Pesan Sistem')
@section('breadcrumb', 'Pesan Sistem')

@section('content')

@php
$groupMeta = [
    'onboarding' => ['icon' => 'bi-person-plus-fill',   'color' => '#6366f1', 'bg' => '#eef2ff', 'label' => 'Onboarding Member'],
    'broadcast'  => ['icon' => 'bi-megaphone-fill',      'color' => '#0ea5e9', 'bg' => '#e0f2fe', 'label' => 'Broadcast & Notifikasi'],
    'group'      => ['icon' => 'bi-people-fill',         'color' => '#059669', 'bg' => '#dcfce7', 'label' => 'Moderasi Grup'],
    'moderation' => ['icon' => 'bi-shield-fill-check',   'color' => '#f59e0b', 'bg' => '#fef3c7', 'label' => 'Moderasi Konten'],
    'bot'        => ['icon' => 'bi-robot',               'color' => '#8b5cf6', 'bg' => '#ede9fe', 'label' => 'Bot & AI'],
    'listing'    => ['icon' => 'bi-tag-fill',            'color' => '#ec4899', 'bg' => '#fce7f3', 'label' => 'Iklan & Listing'],
];
$defaultMeta = ['icon' => 'bi-chat-square-dots-fill', 'color' => '#6b7280', 'bg' => '#f3f4f6', 'label' => 'Lainnya'];

$grouped = $messages->groupBy(fn($m) => $m->group ?? 'lainnya');
$groupOrder = ['onboarding','broadcast','group','moderation','bot','listing'];
$grouped = $grouped->sortBy(fn($_, $k) => in_array($k, $groupOrder) ? array_search($k, $groupOrder) : 99);
@endphp

{{-- Page header --}}
<div class="page-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h1><i class="bi bi-chat-square-text me-2" style="color:#059669;"></i>Pesan Sistem</h1>
            <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Template pesan yang dikirim bot secara otomatis</p>
        </div>
        <span style="background:#f3f4f6;border-radius:20px;padding:.3rem .9rem;font-size:.82rem;color:#4b5563;font-weight:600;">
            {{ $messages->count() }} pesan &middot; {{ $grouped->count() }} grup
        </span>
    </div>
</div>

{{-- Flash --}}
@if(session('success'))
<div class="alert mb-3" style="background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;border-radius:10px;padding:.75rem 1rem;">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
</div>
@endif

{{-- Search --}}
<div class="mb-5" style="position:relative;">
    <i class="bi bi-search" style="position:absolute;left:.85rem;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.9rem;"></i>
    <input type="text" id="msgSearch" placeholder="Cari pesan berdasarkan nama, key, atau isi pesan..."
           autocomplete="off"
           style="width:100%;padding:.65rem 1rem .65rem 2.4rem;border:1px solid #e5e7eb;border-radius:10px;font-size:.875rem;outline:none;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.06);"
           oninput="filterMessages(this.value)">
    <button id="searchClear" onclick="document.getElementById('msgSearch').value='';filterMessages('');"
            style="display:none;position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:#9ca3af;cursor:pointer;font-size:1rem;">
        <i class="bi bi-x-circle-fill"></i>
    </button>
</div>

{{-- No-results placeholder --}}
<div id="noResults" style="display:none;text-align:center;padding:3rem 1rem;color:#9ca3af;">
    <i class="bi bi-search" style="font-size:2rem;"></i>
    <div style="margin-top:.5rem;font-size:.9rem;">Tidak ada pesan yang cocok dengan pencarian</div>
</div>

{{-- Grouped messages --}}
@foreach($grouped as $groupKey => $groupMessages)
@php $meta = $groupMeta[$groupKey] ?? $defaultMeta; @endphp
<div class="msg-group mb-5" data-group="{{ $groupKey }}">
    {{-- Group header --}}
    <div class="d-flex align-items-center gap-2 mb-4" style="cursor:pointer;padding:.5rem .75rem;border-radius:10px;background:{{ $meta['bg'] }};" onclick="toggleGroup('{{ $groupKey }}')">
        <div style="width:36px;height:36px;border-radius:9px;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 1px 3px rgba(0,0,0,.08);">
            <i class="{{ $meta['icon'] }}" style="color:{{ $meta['color'] }};font-size:1rem;"></i>
        </div>
        <span style="font-weight:700;font-size:1rem;color:#111827;">{{ $meta['label'] }}</span>
        <span style="background:#fff;color:{{ $meta['color'] }};border-radius:20px;padding:2px 10px;font-size:.75rem;font-weight:700;box-shadow:0 1px 2px rgba(0,0,0,.08);">{{ $groupMessages->count() }} pesan</span>
        <div style="flex:1;"></div>
        <i class="bi bi-chevron-down group-chevron" id="chevron-{{ $groupKey }}" style="color:{{ $meta['color'] }};font-size:.8rem;transition:transform .2s;"></i>
    </div>

    {{-- Cards --}}
    <div class="group-body" id="grpbody-{{ $groupKey }}">
        <div class="row g-4">
            @foreach($groupMessages as $msg)
            <div class="col-12 msg-card-wrap"
                 data-label="{{ strtolower($msg->label) }}"
                 data-key="{{ strtolower($msg->key) }}"
                 data-body="{{ strtolower($msg->body ?? '') }}">
                <div class="card border-0 shadow-sm {{ $msg->is_active ? '' : 'opacity-60' }}"
                     style="border-radius:12px;overflow:hidden;border-left:4px solid {{ $meta['color'] }} !important;">
                    {{-- Card header --}}
                    <div style="background:#f9fafb;border-bottom:1px solid #f0f0f0;padding:1rem 1.4rem;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                        <div style="flex:1;min-width:0;">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span style="font-weight:700;font-size:.875rem;color:#111827;">{{ $msg->label }}</span>
                                @if(!$msg->is_active)
                                <span style="background:#fef3c7;color:#92400e;border-radius:4px;padding:1px 6px;font-size:.7rem;font-weight:600;">Nonaktif</span>
                                @endif
                            </div>
                            <code style="font-size:.73rem;color:{{ $meta['color'] }};background:{{ $meta['bg'] }};border-radius:4px;padding:2px 8px;margin-top:5px;display:inline-block;font-weight:600;">{{ $msg->key }}</code>
                            @if($msg->description)
                            <div style="font-size:.8rem;color:#6b7280;margin-top:6px;line-height:1.5;">{{ $msg->description }}</div>
                            @endif
                        </div>
                        @if(!empty($msg->placeholders))
                        <div style="text-align:right;flex-shrink:0;">
                            <div style="font-size:.7rem;color:#9ca3af;margin-bottom:3px;">Placeholder:</div>
                            <div class="d-flex gap-1 flex-wrap justify-content-end">
                                @foreach($msg->placeholders as $ph)
                                <code style="background:#fff;border:1px solid #d1d5db;border-radius:4px;padding:1px 6px;font-size:.7rem;color:#374151;">{!! e('{' . $ph . '}') !!}</code>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    {{-- Form --}}
                    <div class="card-body" style="padding:1.25rem 1.4rem;">
                        <form action="{{ route('system-messages.update', $msg) }}" method="POST">
                            @csrf @method('PUT')
                            <textarea name="body"
                                      class="form-control font-monospace"
                                      rows="7"
                                      placeholder="Isi pesan..."
                                      style="font-size:.84rem;line-height:1.75;resize:vertical;border-color:#e5e7eb;border-radius:8px;padding:.75rem 1rem;">{{ $msg->body }}</textarea>
                            <div style="font-size:.73rem;color:#9ca3af;margin-top:.5rem;margin-bottom:1rem;">
                                Gunakan <code>*teks*</code> untuk <strong>tebal</strong>, <code>_teks_</code> untuk <em>miring</em>. Emoji didukung penuh (salin &amp; tempel langsung di sini).
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                           id="active_{{ $msg->id }}" {{ $msg->is_active ? 'checked' : '' }}>
                                    <label class="form-check-label" for="active_{{ $msg->id }}"
                                           style="font-size:.83rem;color:#6b7280;">Aktifkan pesan ini</label>
                                </div>
                                <button type="submit" class="btn btn-sm"
                                        style="background:#059669;color:#fff;border:none;border-radius:8px;padding:.4rem 1.1rem;font-weight:600;font-size:.83rem;">
                                    <i class="bi bi-floppy me-1"></i>Simpan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endforeach

{{-- Hint --}}
<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:.75rem 1rem;font-size:.78rem;color:#15803d;margin-top:1rem;">
    <i class="bi bi-info-circle me-1"></i>
    <strong>Placeholder:</strong> Tulis <code>{name}</code>, <code>{phone}</code>, dsb. — sistem akan menggantinya dengan data nyata saat pesan dikirim.
</div>

@push('scripts')
<script>
// ── Group collapse toggle ─────────────────────────────────────────────────────
function toggleGroup(key) {
    const body = document.getElementById('grpbody-' + key);
    const chev = document.getElementById('chevron-' + key);
    const collapsed = body.style.display === 'none';
    body.style.display = collapsed ? '' : 'none';
    chev.style.transform = collapsed ? '' : 'rotate(-90deg)';
}

// ── Search filter ─────────────────────────────────────────────────────────────
function filterMessages(q) {
    const term = q.trim().toLowerCase();
    document.getElementById('searchClear').style.display = term ? 'block' : 'none';

    let visible = 0;
    document.querySelectorAll('.msg-card-wrap').forEach(card => {
        const hit = !term
            || card.dataset.label.includes(term)
            || card.dataset.key.includes(term)
            || card.dataset.body.includes(term);
        card.style.display = hit ? '' : 'none';
        if (hit) visible++;
    });

    // Show groups that still have visible cards; auto-expand groups with matches
    document.querySelectorAll('.msg-group').forEach(grp => {
        const anyVisible = [...grp.querySelectorAll('.msg-card-wrap')]
            .some(c => c.style.display !== 'none');
        grp.style.display = anyVisible ? '' : 'none';
        if (term && anyVisible) {
            const key = grp.dataset.group;
            const body = document.getElementById('grpbody-' + key);
            const chev = document.getElementById('chevron-' + key);
            if (body) { body.style.display = ''; }
            if (chev) { chev.style.transform = ''; }
        }
    });

    document.getElementById('noResults').style.display = visible === 0 && term ? 'block' : 'none';
}

// Focus search with Ctrl+F equivalent (/ key)
document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement.tagName !== 'TEXTAREA' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        document.getElementById('msgSearch').focus();
    }
});
</script>
@endpush
@endsection
