@extends('layouts.app')
@section('title', 'Pengaturan')
@section('breadcrumb', 'Pengaturan')

@section('content')
<div class="page-header">
    <h1><i class="bi bi-gear me-2" style="color:#6b7280;"></i>Pengaturan</h1>
    <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Konfigurasi sistem dan integrasi API</p>
</div>

<div class="page-body">
    <div class="row g-3">
        <!-- Webhook -->
        <div class="col-12 col-lg-6">
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-webhook me-2" style="color:#15803d;"></i>Webhook URL</span>
                </div>
                <div class="card-body">
                    <p style="font-size:.82rem;color:#374151;margin-bottom:.75rem;">Daftarkan URL berikut ke pengaturan webhook di whacenter.com.</p>
                    <div class="input-group">
                        <input type="text" id="webhookUrl" class="form-control" value="{{ url('/api/webhook/whacenter') }}" readonly style="font-size:.82rem;font-family:monospace;">
                        <button class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#4f46e5;" onclick="copyWebhook()"><i class="bi bi-clipboard" id="copyIcon"></i></button>
                    </div>
                    <div style="margin-top:.75rem;padding:.6rem .75rem;background:#f8fafc;border-radius:8px;font-size:.75rem;color:#6b7280;">
                        <i class="bi bi-info-circle me-1" style="color:#4f46e5;"></i>
                        Method: <strong style="color:#374151;">POST</strong> &nbsp;|&nbsp; Content-Type: <strong style="color:#374151;">application/json</strong>
                    </div>
                </div>
            </div>

            <!-- Whacenter Test -->
            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-whatsapp me-2" style="color:#15803d;"></i>Test WhaCentre</span>
                </div>
                <div class="card-body">
                    <p style="font-size:.82rem;color:#374151;margin-bottom:.75rem;">Kirim pesan WhatsApp tes via whacenter.com API.</p>
                    <ul class="nav nav-tabs mb-3" id="waTabs">
                        <li class="nav-item">
                            <button class="nav-link active" style="font-size:.8rem;" data-bs-toggle="tab" data-bs-target="#tabPrivate">Pesan Pribadi</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" style="font-size:.8rem;" data-bs-toggle="tab" data-bs-target="#tabGroup">Pesan Grup</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <!-- Personal message -->
                        <div class="tab-pane fade show active" id="tabPrivate">
                            <form method="POST" action="{{ route('settings.test-whacenter') }}">
                                @csrf
                                <input type="hidden" name="type" value="private">
                                <div class="mb-3">
                                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nomor Tujuan</label>
                                    <input type="text" name="number" class="form-control" placeholder="628123456789" required>
                                    <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem;">Format: 628xxx (tanpa + atau spasi)</div>
                                </div>
                                <div class="mb-3">
                                        <label class="form-label" style="color:#374151;font-size:.82rem;">Pesan</label>
                                    <textarea name="message" class="form-control" rows="3" required>Halo! Ini adalah pesan test dari Marketplace Jamaah AI. Sistem aktif dan berjalan normal.</textarea>
                                </div>
                                <button type="submit" class="btn btn-sm" style="background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;">
                                    <i class="bi bi-send me-2"></i>Kirim ke Pribadi
                                </button>
                            </form>
                        </div>
                        <!-- Group message -->
                        <div class="tab-pane fade" id="tabGroup">
                            <form method="POST" action="{{ route('settings.test-whacenter') }}">
                                @csrf
                                <input type="hidden" name="type" value="group">
                                <div class="mb-3">
                                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nama Grup</label>
                                    <input type="text" name="group" class="form-control" placeholder="Marketplace Jamaah" required>
                                    <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem;">Nama grup sesuai di WhatsApp</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" style="color:#374151;font-size:.82rem;">Pesan</label>
                                    <textarea name="message" class="form-control" rows="3" required>Halo grup! Test pesan broadcast dari Marketplace Jamaah AI.</textarea>
                                </div>
                                <button type="submit" class="btn btn-sm" style="background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;">
                                    <i class="bi bi-people me-2"></i>Kirim ke Grup
                                </button>
                            </form>
                        </div>
                    </div>

                    @if(session('whacenter_result'))
                    <div class="mt-3 p-3 rounded {{ session('whacenter_result.success') ? 'border-success' : 'border-danger' }}" style="background:{{ session('whacenter_result.success') ? '#dcfce7' : '#fee2e2' }};border:1px solid {{ session('whacenter_result.success') ? '#bbf7d0' : '#fca5a5' }};">
                        <div style="font-size:.82rem;color:{{ session('whacenter_result.success') ? '#15803d' : '#b91c1c' }};font-weight:600;">
                            <i class="bi bi-{{ session('whacenter_result.success') ? 'check-circle' : 'x-circle' }} me-2"></i>
                            {{ session('whacenter_result.success') ? 'Berhasil dikirim' : 'Gagal: '.session('whacenter_result.message') }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- API Configs -->
        <div class="col-12 col-lg-6">
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-key me-2" style="color:#92400e;"></i>Konfigurasi API</span>
                </div>
                <div class="card-body">
                    @php
                        $hasWhacenter = !empty($settings['whacenter_device_id']) || !empty($settings['whacenter_url']);
                        $hasGemini    = !empty($settings['gemini_key']);
                        $hasAnyApi    = $hasWhacenter || $hasGemini;
                    @endphp

                    @if(!$hasAnyApi)
                        <p style="color:#9ca3af;font-size:.82rem;margin:0;">Tidak ada konfigurasi API yang aktif.</p>
                    @endif

                    @if($hasWhacenter)
                    <div class="{{ $hasGemini ? 'mb-4' : '' }}">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-whatsapp" style="color:#25d166;font-size:1rem;"></i>
                            <span style="font-weight:600;color:#111827;font-size:.875rem;">WhaCentre</span>
                        </div>
                        <div class="p-3 rounded" style="background:#f8fafc;">
                            @if(!empty($settings['whacenter_device_id']))
                            <div class="{{ !empty($settings['whacenter_url']) ? 'mb-2' : '' }}">
                                <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;">Device ID</div>
                                <code style="font-size:.78rem;color:#374151;">{{ $settings['whacenter_device_id'] }}</code>
                            </div>
                            @endif
                            @if(!empty($settings['whacenter_url']))
                            <div>
                                <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;">API URL</div>
                                <code style="font-size:.78rem;color:#374151;">{{ $settings['whacenter_url'] }}</code>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if($hasGemini)
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-stars" style="color:#818cf8;font-size:1rem;"></i>
                            <span style="font-weight:600;color:#111827;font-size:.875rem;">Google Gemini AI</span>
                        </div>
                        <div class="p-3 rounded" style="background:#f8fafc;">
                            <div class="{{ !empty($settings['gemini_model']) ? 'mb-2' : '' }}">
                                <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;">API Key</div>
                                <code style="font-size:.78rem;color:#374151;">{{ Str::mask($settings['gemini_key'], '*', 6, -4) }}</code>
                            </div>
                            @if(!empty($settings['gemini_model']))
                            <div>
                                <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;">Model</div>
                                <code style="font-size:.78rem;color:#374151;">{{ $settings['gemini_model'] }}</code>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- System Info -->
            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-info-circle me-2" style="color:#0369a1;"></i>Info Sistem</span>
                </div>
                <div class="card-body">
                    <table class="table mb-0" style="font-size:.82rem;">
                        <tbody>
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;border-top:0;">Laravel</td>
                                <td class="text-end" style="padding:.5rem 0;border-top:0;color:#374151;">{{ app()->version() }}</td>
                            </tr>
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">PHP</td>
                                <td class="text-end" style="padding:.5rem 0;color:#374151;">{{ PHP_VERSION }}</td>
                            </tr>
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Database</td>
                                <td class="text-end" style="padding:.5rem 0;color:#374151;">PostgreSQL</td>
                            </tr>
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Queue</td>
                                <td class="text-end" style="padding:.5rem 0;color:#374151;">{{ ucfirst(config('queue.default')) }}</td>
                            </tr>
                            <tr style="border-color:#e5e7eb;">
                                <td style="color:#6b7280;padding:.5rem 0;">Broadcast</td>
                                <td class="text-end" style="padding:.5rem 0;color:#374151;">Laravel Reverb</td>
                            </tr>
                            <tr style="border-color:transparent;">
                                <td style="color:#6b7280;padding:.5rem 0 0;">Environment</td>
                                <td class="text-end" style="padding:.5rem 0 0;">
                                    <span class="badge" style="background:{{ app()->environment('production') ? '#dcfce7' : '#fef9c3' }};color:{{ app()->environment('production') ? '#15803d' : '#92400e' }};">{{ ucfirst(app()->environment()) }}</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

        <!-- DB Settings (editable) -->
        @if($dbSettings->count())
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-sliders me-2" style="color:#7c3aed;"></i>Pengaturan Umum</span>
                    <span style="font-size:.75rem;color:#6b7280;">Disimpan di database</span>
                </div>
                <div class="card-body">
                    @if(session('saved'))
                    <div class="alert alert-success py-2" style="font-size:.82rem;background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;">
                        <i class="bi bi-check-circle me-1"></i>{{ session('saved') }}
                    </div>
                    @endif
                    <form method="POST" action="{{ route('settings.update') }}">
                        @csrf
                        @foreach($dbSettings as $group => $items)
                        @if($group === 'ai_prompts') @continue @endif
                        <div class="mb-4" id="{{ $group }}">
                            <div style="font-size:.7rem;text-transform:uppercase;color:#9ca3af;font-weight:700;letter-spacing:.05em;margin-bottom:.75rem;border-bottom:1px solid #f3f4f6;padding-bottom:.4rem;">
                                {{ ucfirst($group) }}
                            </div>
                            @foreach($items as $item)
                            <div class="mb-3">
                                <label class="form-label" style="font-size:.82rem;font-weight:600;color:#374151;">
                                    {{ $item->label }}
                                </label>
                                @if($item->type === 'textarea')
                                    <textarea name="setting[{{ $item->key }}]" class="form-control" rows="{{ $group === 'ai_prompts' ? 8 : 3 }}" style="font-size:.82rem;{{ $group === 'ai_prompts' ? 'font-family:monospace;' : '' }}">{{ $item->value }}</textarea>
                                @elseif($item->type === 'boolean')
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="setting[{{ $item->key }}]" value="1" {{ $item->value ? 'checked' : '' }}>
                                    </div>
                                @else
                                    <input type="{{ in_array($item->type, ['url','number']) ? $item->type : 'text' }}" name="setting[{{ $item->key }}]" class="form-control" value="{{ $item->value }}" placeholder="{{ $item->type === 'url' ? 'https://' : '' }}" style="font-size:.82rem;" {{ $item->type === 'number' ? 'min=1' : '' }}>
                                @endif
                                @if($item->description)
                                <div style="font-size:.72rem;color:#6b7280;margin-top:.3rem;">{{ $item->description }}</div>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        @endforeach
                        <button type="submit" class="btn btn-sm" style="background:#7c3aed;color:#fff;border:none;">
                            <i class="bi bi-save me-2"></i>Simpan Pengaturan
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

<script>
function copyWebhook() {
    const url = document.getElementById('webhookUrl');
    url.select();
    document.execCommand('copy');
    const icon = document.getElementById('copyIcon');
    icon.className = 'bi bi-clipboard-check';
    icon.style.color = '#4ade80';
    setTimeout(() => { icon.className = 'bi bi-clipboard'; icon.style.color = ''; }, 2000);
}
</script>
@endsection
