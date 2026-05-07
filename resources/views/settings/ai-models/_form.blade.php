@php
    $val = fn($k, $default = '') => old($k, $m ? $m->{$k} : $default);
@endphp

<div class="mb-3">
    <label>Nama (label)</label>
    <input type="text" name="name" class="form-control" required maxlength="120"
           value="{{ $val('name') }}" placeholder="mis. Gemini Flash — Primary Text">
    <small class="help">Label internal untuk identifikasi di dashboard.</small>
</div>

<div class="row g-2">
    <div class="col-md-6 mb-3">
        <label>Provider</label>
        <select name="provider" class="form-select" required>
            @foreach($providers as $key => $label)
                <option value="{{ $key }}" {{ $val('provider', 'gemini') === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label>Role</label>
        @if($isEdit)
            <select name="role" class="form-select" required>
                @foreach($roles as $key => $label)
                    <option value="{{ $key }}" {{ $val('role') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        @else
            {{-- For add modal, role is set via hidden input from the role-card "Tambah" button --}}
            <select name="role" class="form-select" required>
                @foreach($roles as $key => $label)
                    <option value="{{ $key }}" {{ old('role') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </div>
</div>

<div class="mb-3">
    <label>Model name</label>
    <input type="text" name="model" class="form-control" required maxlength="200"
           value="{{ $val('model') }}"
           placeholder="mis. gemini-flash-latest, gemini-2.5-flash, llama-3.3-70b-versatile, claude-haiku-4-5">
    <small class="help">String persis yang dikirim ke API. Lihat docs provider untuk daftar.</small>
</div>

<div class="mb-3">
    <label>API Key
        <span class="badge ms-1" style="background:#fef3c7;color:#92400e;font-size:.6rem;font-weight:600;">
            <i class="bi bi-shield-lock me-1"></i>terenkripsi
        </span>
    </label>
    <div class="input-group">
        <input type="password" name="api_key" class="form-control"
               id="apikey-{{ $m?->id ?? 'new' }}"
               autocomplete="new-password"
               value=""
               placeholder="{{ $isEdit ? 'Kosongkan untuk pertahankan key sekarang' : 'Tempel API key dari dashboard provider' }}"
               style="font-family:monospace;font-size:.82rem;">
        <button type="button" class="btn btn-outline-secondary"
                onclick="(function(id){var el=document.getElementById(id);el.type=el.type==='password'?'text':'password';})('apikey-{{ $m?->id ?? 'new' }}')">
            <i class="bi bi-eye"></i>
        </button>
    </div>
    @if($isEdit && $m && $m->api_key)
        <small class="help">Nilai aktif: <code style="color:#1d4ed8;">{{ $m->masked_key }}</code></small>
    @elseif(!$isEdit)
        <small class="help">Akan dienkripsi sebelum disimpan menggunakan APP_KEY Laravel.</small>
    @endif
</div>

<div class="mb-3">
    <label>Endpoint URL <span class="text-muted" style="font-weight:400;">(opsional)</span></label>
    <input type="url" name="endpoint" class="form-control" maxlength="500"
           value="{{ $val('endpoint') }}"
           placeholder="Kosongkan untuk pakai default per provider">
    <small class="help">
        Default: Gemini → <code>https://generativelanguage.googleapis.com/v1beta/models</code>;
        Groq → <code>https://api.groq.com/openai/v1/chat/completions</code>;
        Anthropic → <code>https://api.anthropic.com/v1/messages</code>.
    </small>
</div>

<div class="row g-2">
    <div class="col-md-6 mb-3">
        <label>Priority</label>
        <input type="number" name="priority" class="form-control" required min="1" max="999"
               value="{{ $val('priority', 10) }}">
        <small class="help">Lebih kecil = dicoba duluan dalam role yang sama.</small>
    </div>
    <div class="col-md-6 mb-3">
        <label>&nbsp;</label>
        <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="active-{{ $m?->id ?? 'new' }}"
                   {{ ($m ? $m->is_active : old('is_active', true)) ? 'checked' : '' }}>
            <label class="form-check-label" for="active-{{ $m?->id ?? 'new' }}" style="font-size:.85rem;">Aktif</label>
        </div>
    </div>
</div>

<div class="mb-3">
    <label>Catatan <span class="text-muted" style="font-weight:400;">(opsional)</span></label>
    <textarea name="notes" class="form-control" rows="2" maxlength="1000">{{ $val('notes') }}</textarea>
</div>
