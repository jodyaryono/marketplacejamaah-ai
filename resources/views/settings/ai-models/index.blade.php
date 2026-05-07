@extends('layouts.app')
@section('title', 'AI Models')
@section('breadcrumb', 'Pengaturan / AI Models')

@push('styles')
<style>
    .role-card { border-radius: 14px; border: 1px solid #e5e7eb; background: #fff; margin-bottom: 1rem; }
    .role-card .role-head { padding: .85rem 1.15rem; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .role-card .role-head h3 { font-size: .9rem; font-weight: 700; margin: 0; color: #111827; }
    .role-card .role-head .role-sub { font-size: .72rem; color: #6b7280; }
    .role-pill { font-size: .65rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 12px; letter-spacing: .03em; }
    .role-pill.primary_text    { background:#dbeafe; color:#1d4ed8; }
    .role-pill.fallback_text   { background:#fef3c7; color:#92400e; }
    .role-pill.primary_vision  { background:#dcfce7; color:#15803d; }
    .role-pill.fallback_vision { background:#fed7aa; color:#9a3412; }
    .role-pill.disabled        { background:#f3f4f6; color:#6b7280; }
    .provider-pill { font-size: .65rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; }
    .provider-pill.gemini     { background:#ede9fe; color:#6d28d9; }
    .provider-pill.groq       { background:#ffe4e6; color:#be123c; }
    .provider-pill.openai     { background:#d1fae5; color:#047857; }
    .provider-pill.anthropic  { background:#fef3c7; color:#92400e; }
    .provider-pill.openrouter { background:#e0e7ff; color:#3730a3; }
    .provider-pill.custom     { background:#f3f4f6; color:#6b7280; }
    .model-row { display: grid; grid-template-columns: 1.6fr 1fr 1fr 0.5fr 0.6fr 1.2fr; gap: .75rem; padding: .75rem 1.15rem; border-top: 1px solid #f3f4f6; align-items: center; font-size: .82rem; }
    .model-row:first-child { border-top: none; }
    .model-row.inactive { opacity: .55; background:#fafafa; }
    .model-row code { font-size: .76rem; color: #374151; word-break: break-all; }
    .model-row .actions { display: flex; gap: .35rem; flex-wrap: wrap; justify-content: flex-end; }
    .model-row .actions button, .model-row .actions a { font-size: .72rem; padding: .25rem .55rem; }

    /* Mobile: stack each row */
    @media (max-width: 768px) {
        .model-row { grid-template-columns: 1fr; gap: .35rem; padding: .85rem 1rem; }
        .model-row > div { display: flex; gap: .5rem; align-items: baseline; flex-wrap: wrap; }
        .model-row > div::before { content: attr(data-label); font-size: .65rem; text-transform: uppercase; color: #9ca3af; font-weight: 700; min-width: 70px; }
        .model-row .actions { justify-content: flex-start; padding-top: .5rem; border-top: 1px dashed #e5e7eb; margin-top: .25rem; }
        .model-row .actions::before { display: none; }
    }
    .model-row > div::before { display: none; }
    @media (max-width: 768px) { .model-row > div::before { display: inline; } }

    .add-row-btn { padding: .55rem 1.15rem; border: 1px dashed #d1d5db; background: #fafafa; color:#374151; font-size:.78rem; cursor:pointer; width:100%; text-align:left; }
    .add-row-btn:hover { background:#f3f4f6; color:#1d4ed8; border-color:#93c5fd; }

    .form-modal label { font-size:.78rem; font-weight:600; color:#374151; }
    .form-modal .form-control, .form-modal .form-select { font-size:.85rem; }
    .form-modal small.help { font-size:.7rem; color:#6b7280; }

    .test-result-inline { font-size:.7rem; padding:3px 8px; border-radius:10px; margin-left:.35rem; }
    .test-result-inline.ok { background:#dcfce7; color:#15803d; }
    .test-result-inline.fail { background:#fee2e2; color:#b91c1c; }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-cpu me-2" style="color:#7c3aed;"></i>AI Models</h1>
        <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">
            Kelola model AI: provider, model name, API key (terenkripsi), peran, dan urutan failover.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" id="btn-reveal-all" class="btn btn-sm btn-outline-warning" style="font-size:.78rem;"
                onclick="revealAll()" title="Tampilkan semua key sekaligus untuk match dengan billing dashboard">
            <i class="bi bi-eye me-1"></i>Tampilkan Semua Key
        </button>
        <a href="{{ route('settings.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.78rem;">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>
</div>

<div class="page-body">

    @if(session('saved'))
    <div class="alert py-2 mb-3" style="font-size:.82rem;background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;">
        <i class="bi bi-check-circle me-1"></i>{{ session('saved') }}
    </div>
    @endif

    @if($errors->any())
    <div class="alert py-2 mb-3" style="font-size:.82rem;background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    @php $testResult = session('test_result'); @endphp

    {{-- Group rendering: one card per role (skip disabled to bottom) --}}
    @php
        $roleOrder = ['primary_text', 'fallback_text', 'primary_vision', 'fallback_vision', 'disabled'];
        $roleHints = [
            'primary_text'    => 'Model utama untuk semua agen text (klasifikasi, ekstraksi, balasan bot). Yang priority terendah dipakai duluan.',
            'fallback_text'   => 'Dipakai bila primary_text gagal atau circuit breaker terbuka. Walk-down dari priority terendah.',
            'primary_vision'  => 'Model utama untuk analisis gambar (KTP, foto barang).',
            'fallback_vision' => 'Dipakai bila primary_vision gagal.',
            'disabled'        => 'Model dinonaktifkan — tidak dipanggil siapapun. Disimpan untuk referensi.',
        ];
    @endphp

    @foreach($roleOrder as $role)
        @php $items = $modelsByRole[$role] ?? collect(); @endphp
        <div class="role-card">
            <div class="role-head">
                <div>
                    <span class="role-pill {{ $role }}">{{ $roles[$role] }}</span>
                    <span class="role-sub ms-2">{{ $roleHints[$role] ?? '' }}</span>
                </div>
                <button type="button" class="btn btn-sm btn-primary"
                        style="font-size:.74rem;background:#1d4ed8;border:none;"
                        data-bs-toggle="modal" data-bs-target="#addModal"
                        onclick="document.getElementById('add-role-input').value='{{ $role }}'; document.getElementById('addModalLabel').textContent='Tambah Model: {{ $roles[$role] }}'">
                    <i class="bi bi-plus-lg me-1"></i>Tambah
                </button>
            </div>

            @if($items->count() === 0)
                <div style="padding: 1rem 1.15rem; color:#9ca3af; font-size:.8rem; font-style:italic;">
                    Belum ada model untuk role ini.
                </div>
            @else
                @foreach($items as $m)
                <div class="model-row {{ $m->is_active ? '' : 'inactive' }}">
                    <div data-label="Nama">
                        <strong>{{ $m->name }}</strong>
                        @if(!$m->is_active)
                            <span class="badge ms-1" style="background:#f3f4f6;color:#6b7280;font-size:.6rem;">nonaktif</span>
                        @endif
                        @if($m->notes)
                            <div class="text-muted" style="font-size:.7rem;margin-top:2px;">{{ $m->notes }}</div>
                        @endif
                    </div>
                    <div data-label="Provider">
                        <span class="provider-pill {{ $m->provider }}">{{ $providers[$m->provider] ?? $m->provider }}</span>
                    </div>
                    <div data-label="Model">
                        <code>{{ $m->model }}</code>
                    </div>
                    <div data-label="Priority" class="text-center">
                        <span class="badge" style="background:#eff6ff;color:#1d4ed8;font-weight:700;">{{ $m->priority }}</span>
                    </div>
                    <div data-label="API Key">
                        <div class="d-flex align-items-center gap-1 flex-wrap" id="keybox-{{ $m->id }}">
                            <code id="keytext-{{ $m->id }}" style="font-size:.72rem;color:#9333ea;word-break:break-all;">{{ $m->masked_key }}</code>
                            @if($m->api_key)
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1"
                                    style="font-size:.7rem;line-height:1;"
                                    onclick="revealKey({{ $m->id }})"
                                    title="Tampilkan key lengkap">
                                <i class="bi bi-eye" id="keyicon-{{ $m->id }}"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1"
                                    style="font-size:.7rem;line-height:1;"
                                    onclick="copyKey({{ $m->id }})"
                                    title="Copy key ke clipboard">
                                <i class="bi bi-clipboard" id="copyicon-{{ $m->id }}"></i>
                            </button>
                            @endif
                        </div>
                        @if($m->api_key)
                            <div class="text-muted" style="font-size:.65rem;margin-top:2px;">
                                last4: <strong style="color:#1d4ed8;font-family:monospace;">{{ substr($m->api_key ?? '', -4) }}</strong>
                                — cocokkan dengan provider dashboard untuk identifikasi billing
                            </div>
                        @endif
                        @if($testResult && ($testResult['id'] ?? null) === $m->id)
                            <span class="test-result-inline {{ $testResult['ok'] ? 'ok' : 'fail' }}" title="{{ $testResult['error'] ?? $testResult['response'] ?? '' }}">
                                @if($testResult['ok'])
                                    ✅ {{ $testResult['latency'] ?? '?' }}ms
                                @else
                                    ❌ test gagal
                                @endif
                            </span>
                        @endif
                    </div>
                    <div data-label="Aksi" class="actions">
                        <form method="POST" action="{{ route('ai-models.test', $m) }}" style="display:inline;">@csrf
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Test ping ke API"><i class="bi bi-send"></i> Test</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#editModal-{{ $m->id }}"
                                title="Edit"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('ai-models.toggle', $m) }}" style="display:inline;">@csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ $m->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                <i class="bi bi-{{ $m->is_active ? 'pause' : 'play' }}-circle"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('ai-models.destroy', $m) }}" style="display:inline;"
                              onsubmit="return confirm('Hapus model {{ addslashes($m->name) }}? Tidak bisa dibatalkan.')">@csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>

                {{-- Per-row edit modal --}}
                <div class="modal fade form-modal" id="editModal-{{ $m->id }}" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-fullscreen-md-down">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('ai-models.update', $m) }}">
                                @csrf @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title" style="font-size:1rem;">Edit Model: {{ $m->name }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    @include('settings.ai-models._form', ['m' => $m, 'roles' => $roles, 'providers' => $providers, 'isEdit' => true])
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    @endforeach

    {{-- Quick reference card --}}
    <div class="card mt-3" style="border:1px solid #e5e7eb;">
        <div class="card-body" style="font-size:.78rem;color:#374151;">
            <strong style="color:#1d4ed8;"><i class="bi bi-info-circle me-1"></i>Cara kerja failover:</strong>
            <ol class="mb-0 mt-2 ps-3">
                <li>Untuk setiap permintaan teks, sistem coba <strong>primary_text</strong> dengan priority terendah (mis. 10) dulu.</li>
                <li>Bila gagal (HTTP error / rate limit / circuit breaker), pindah ke entry priority berikutnya pada role yang sama, lalu ke <strong>fallback_text</strong>.</li>
                <li>Sama persis untuk vision: primary_vision → fallback_vision.</li>
                <li>API key disimpan terenkripsi (Laravel <code>Crypt::encryptString</code> dengan APP_KEY). Edit kosongkan field key untuk mempertahankan nilai sekarang.</li>
                <li>Tombol <strong>Test</strong> kirim 1 prompt "PONG" pendek (≤16 token) untuk verifikasi key + endpoint, hampir gratis.</li>
            </ol>
        </div>
    </div>
</div>

@push('scripts')
<script>
const KEY_REVEAL_TIMEOUT_MS = 60000; // auto-hide after 60s for safety
const _keyTimers = {};

async function revealKey(id) {
    const txt = document.getElementById('keytext-' + id);
    const icon = document.getElementById('keyicon-' + id);
    if (!txt || !icon) return;

    // Toggle: if currently revealed, mask it back
    if (txt.dataset.revealed === '1') {
        txt.textContent = txt.dataset.masked || '';
        txt.dataset.revealed = '0';
        icon.className = 'bi bi-eye';
        if (_keyTimers[id]) { clearTimeout(_keyTimers[id]); delete _keyTimers[id]; }
        return;
    }

    // Save masked text so we can restore on toggle
    txt.dataset.masked = txt.textContent;
    icon.className = 'bi bi-hourglass-split';

    try {
        const res = await fetch(`/settings/ai-models/${id}/reveal`, {
            headers: { 'Accept': 'application/json' }
        });
        if (res.status === 401 || res.status === 419) {
            alert('Sesi login berakhir — refresh halaman.');
            window.location.reload();
            return;
        }
        const data = await res.json();
        if (!data.has_key) {
            txt.textContent = '(belum diisi)';
            icon.className = 'bi bi-eye';
            return;
        }
        txt.textContent = data.api_key;
        txt.dataset.revealed = '1';
        txt.dataset.fullkey = data.api_key;
        icon.className = 'bi bi-eye-slash';

        // Auto-hide after timeout
        _keyTimers[id] = setTimeout(() => {
            txt.textContent = txt.dataset.masked || '';
            txt.dataset.revealed = '0';
            icon.className = 'bi bi-eye';
            delete _keyTimers[id];
        }, KEY_REVEAL_TIMEOUT_MS);
    } catch (e) {
        icon.className = 'bi bi-exclamation-triangle text-danger';
        alert('Gagal ambil key: ' + e.message);
    }
}

async function copyKey(id) {
    const txt = document.getElementById('keytext-' + id);
    const icon = document.getElementById('copyicon-' + id);
    if (!txt || !icon) return;

    let key = txt.dataset.fullkey;
    if (!key) {
        // Not yet revealed — fetch it just for copy without changing UI
        try {
            const res = await fetch(`/settings/ai-models/${id}/reveal`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            key = data.api_key;
            txt.dataset.fullkey = key;
        } catch (e) {
            alert('Gagal ambil key: ' + e.message);
            return;
        }
    }
    if (!key) {
        alert('Key kosong untuk model ini');
        return;
    }

    try {
        await navigator.clipboard.writeText(key);
        icon.className = 'bi bi-clipboard-check text-success';
        setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 2000);
    } catch (e) {
        // Fallback for non-HTTPS / older browsers
        const ta = document.createElement('textarea');
        ta.value = key; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        icon.className = 'bi bi-clipboard-check text-success';
        setTimeout(() => { icon.className = 'bi bi-clipboard'; }, 2000);
    }
}

async function revealAll() {
    const btn = document.getElementById('btn-reveal-all');
    if (!confirm('Tampilkan SEMUA API key di halaman ini? Pastikan tidak ada orang lain yang melihat layar Anda.')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Mengambil...';
    // Find all rows with a reveal button (id pattern keytext-N)
    const els = document.querySelectorAll('[id^="keytext-"]');
    const ids = Array.from(els).map(el => Number(el.id.replace('keytext-', '')));
    for (const id of ids) {
        const txt = document.getElementById('keytext-' + id);
        if (txt && txt.dataset.revealed !== '1') await revealKey(id);
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-eye-slash me-1"></i>Sembunyikan Semua';
    btn.onclick = hideAll;
}

function hideAll() {
    const els = document.querySelectorAll('[id^="keytext-"]');
    els.forEach(el => {
        const id = Number(el.id.replace('keytext-', ''));
        if (el.dataset.revealed === '1') revealKey(id); // toggle off
    });
    const btn = document.getElementById('btn-reveal-all');
    btn.innerHTML = '<i class="bi bi-eye me-1"></i>Tampilkan Semua Key';
    btn.onclick = revealAll;
}
</script>
@endpush

{{-- Add Modal --}}
<div class="modal fade form-modal" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content">
            <form method="POST" action="{{ route('ai-models.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel" style="font-size:1rem;">Tambah Model AI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="role" id="add-role-input" value="primary_text">
                    @include('settings.ai-models._form', ['m' => null, 'roles' => $roles, 'providers' => $providers, 'isEdit' => false])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>Tambah Model</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
