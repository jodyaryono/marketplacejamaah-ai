@extends('layouts.app')

@section('title', 'AI Health & Token Check')
@section('breadcrumb', 'AI Health & Token Check')

@push('styles')
<style>
    .health-card { border-radius: 16px; border: 1px solid #d1fae5; background: #fff; padding: 1.25rem 1.5rem; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .status-dot.ok  { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.2); animation: pulse-dot 2s infinite; }
    .status-dot.fail { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.2); }
    .status-dot.unknown { background: #9ca3af; }
    @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.6;transform:scale(1.3)} }

    .bar-track { height: 8px; background: #d1fae5; border-radius: 4px; overflow: hidden; }
    .bar-fill  { height: 100%; border-radius: 4px; background: linear-gradient(90deg, #059669, #10b981); transition: width .4s; }
    .bar-fill.danger { background: linear-gradient(90deg, #ef4444, #f87171); }
    .bar-fill.warn   { background: linear-gradient(90deg, #d97706, #f59e0b); }

    .token-day-bar { display: flex; align-items: flex-end; gap: 4px; height: 80px; }
    .token-day-col { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; }
    .token-day-col .bar { width: 100%; border-radius: 4px 4px 0 0; background: linear-gradient(180deg,#10b981,#059669); min-height: 3px; transition: height .4s; }
    .token-day-col .bar.today { background: linear-gradient(180deg,#f59e0b,#d97706); }
    .token-day-col .lbl { font-size:.65rem; color:#6b7280; }

    .ping-btn { min-width: 110px; }
    .latency-badge { font-size:.7rem; padding:.2rem .55rem; border-radius:20px; }
    .agent-row:hover { background: #f0fdf8 !important; }

    .cost-tag { font-size:.7rem; background:#ecfdf5; color:#059669; border:1px solid #d1fae5; border-radius:20px; padding:.15rem .55rem; font-weight:600; }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1><i class="bi bi-heart-pulse me-2 text-success"></i>AI Health & Token Check</h1>
    <p class="mb-0">Status layanan AI, penggunaan token Gemini, dan performa setiap agen</p>
</div>

<div class="page-body">

    {{-- ── Row 1: Service Status Cards ─────────────────────────────────── --}}
    <div class="row g-3 mb-4">

        {{-- Gemini --}}
        <div class="col-md-4">
            <div class="health-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:38px;height:38px;background:linear-gradient(135deg,#8b5cf6,#6d28d9);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-stars text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">Gemini AI</div>
                            <div style="font-size:.72rem;color:#6b7280;">{{ $geminiModel }}</div>
                        </div>
                    </div>
                    <span id="gemini-dot" class="status-dot {{ $lastGeminiPing ? ($lastGeminiPing['ok'] ? 'ok' : 'fail') : 'unknown' }}"></span>
                </div>
                <div style="font-size:.78rem;color:#374151;">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Ping terakhir</span>
                        <span id="gemini-last-at">{{ $lastGeminiPing ? $lastGeminiPing['at'] : '-' }}</span>
                    </div>
                    <div id="gemini-models-list" class="mb-2" style="font-size:.7rem;">
                        @if(!empty($lastGeminiPing['models']))
                            @foreach($lastGeminiPing['models'] as $m)
                                <div class="d-flex justify-content-between align-items-start mb-1" style="border-bottom:1px dashed #e5e7eb;padding-bottom:3px;">
                                    <div style="flex:1;min-width:0;">
                                        <div><strong>{{ $m['provider'] }}</strong> <span class="text-muted">— {{ $m['role'] }}</span></div>
                                        <div class="text-muted" style="font-size:.65rem;word-break:break-all;">{{ $m['model'] }}</div>
                                    </div>
                                    <div class="text-end ms-2" style="white-space:nowrap;">
                                        @if($m['ok'] === true)
                                            <span class="text-success">✅ OK</span>
                                            @if(!empty($m['latency'])) <span class="text-muted" style="font-size:.65rem;">{{ $m['latency'] }}ms</span> @endif
                                        @elseif($m['ok'] === false)
                                            <span class="text-danger" title="{{ $m['error'] ?? '' }}">❌</span>
                                        @else
                                            <span class="text-muted" style="font-size:.65rem;">{{ ($m['configured'] ?? false) ? 'configured' : 'not set' }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted">Klik Test Ping untuk lihat status semua model.</div>
                        @endif
                    </div>
                </div>
                <button id="btn-ping-gemini" class="btn btn-sm btn-primary w-100 ping-btn" onclick="pingGemini()">
                    <i class="bi bi-send me-1"></i> Test Ping
                </button>
            </div>
        </div>

        {{-- WhaCentre --}}
        <div class="col-md-4">
            <div class="health-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:38px;height:38px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-whatsapp text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">WhaCentre Gateway</div>
                            <div style="font-size:.72rem;color:#6b7280;word-break:break-all;">{{ $waUrl }}</div>
                        </div>
                    </div>
                    <span id="wa-dot" class="status-dot {{ $lastWhacenterPing ? ($lastWhacenterPing['ok'] ? 'ok' : 'fail') : 'unknown' }}"></span>
                </div>
                <div style="font-size:.78rem;color:#374151;">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Ping terakhir</span>
                        <span id="wa-last-at">{{ $lastWhacenterPing ? $lastWhacenterPing['at'] : '-' }}</span>
                    </div>
                    <div id="wa-ping-result" class="mb-2" style="font-size:.72rem;color:#6b7280;min-height:16px;">
                        @if($lastWhacenterPing)
                            @if($lastWhacenterPing['ok'])
                                ✅ {{ $lastWhacenterPing['latency'] ?? '-' }}ms — HTTP {{ $lastWhacenterPing['http'] ?? '' }}
                            @else
                                ❌ {{ $lastWhacenterPing['error'] ?? 'HTTP ' . ($lastWhacenterPing['http'] ?? '?') }}
                            @endif
                        @endif
                    </div>
                </div>
                <button id="btn-ping-wa" class="btn btn-sm btn-success w-100 ping-btn" onclick="pingWa()">
                    <i class="bi bi-send me-1"></i> Test Ping
                </button>
            </div>
        </div>

        {{-- Queue status --}}
        <div class="col-md-4">
            <div class="health-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:38px;height:38px;background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-stack text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">Queue Worker</div>
                            <div style="font-size:.72rem;color:#6b7280;">Job processing pipeline</div>
                        </div>
                    </div>
                    <span id="queue-dot" class="status-dot {{ $lastQueuePing ? ($lastQueuePing['ok'] ? 'ok' : 'fail') : ($stuckJobs === 0 ? 'ok' : 'fail') }}"></span>
                </div>
                <div style="font-size:.78rem;color:#374151;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Job pending</span>
                        <strong id="queue-pending">{{ $lastQueuePing ? $lastQueuePing['pending'] : '-' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Job failed</span>
                        <strong id="queue-failed" class="{{ ($lastQueuePing['recent_failed'] ?? 0) > 0 ? 'text-danger' : (($lastQueuePing['failed'] ?? 0) > 0 ? 'text-muted' : '') }}">{{ $lastQueuePing ? ($lastQueuePing['failed'] . (($lastQueuePing['recent_failed'] ?? 0) > 0 ? " ({$lastQueuePing['recent_failed']} dlm 1 jam)" : '')) : '-' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Sukses 1 jam terakhir</span>
                        <strong id="queue-success">{{ $lastQueuePing ? $lastQueuePing['success_last_1h'] : '-' }}</strong>
                    </div>
                    <div id="queue-ping-result" class="mb-2" style="font-size:.72rem;color:#6b7280;min-height:16px;">
                        @if($lastQueuePing)
                            @if($lastQueuePing['ok']) ✅ OK · {{ $lastQueuePing['latency'] }}ms
                            @else ❌ {{ $lastQueuePing['error'] ?? 'Ada masalah' }}
                            @endif
                        @endif
                    </div>
                </div>
                <button id="btn-ping-queue" class="btn btn-sm btn-warning w-100 ping-btn" onclick="pingQueue()">
                    <i class="bi bi-send me-1"></i> Test Ping
                </button>
            </div>
        </div>
    </div>

    {{-- ── Row 1c: Database + System health cards ─────────────────────────── --}}
    <div class="row g-3 mb-4">

        {{-- Database --}}
        <div class="col-md-6">
            <div class="health-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:38px;height:38px;background:linear-gradient(135deg,#0ea5e9,#0284c7);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-database text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">Database (PostgreSQL)</div>
                            <div style="font-size:.72rem;color:#6b7280;" id="db-version">{{ $lastDbPing ? Str::limit($lastDbPing['version'] ?? '-', 40) : 'belum di-ping' }}</div>
                        </div>
                    </div>
                    <span id="db-dot" class="status-dot {{ $lastDbPing ? ($lastDbPing['ok'] ? 'ok' : 'fail') : 'unknown' }}"></span>
                </div>
                <div style="font-size:.78rem;color:#374151;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Ukuran DB</span>
                        <strong id="db-size">{{ $lastDbPing ? ($lastDbPing['size_mb'] ?? '-') . ' MB' : '-' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Jobs pending</span>
                        <strong id="db-pending">{{ $lastDbPing ? ($lastDbPing['pending_jobs'] ?? '-') : '-' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Jobs failed</span>
                        <strong id="db-failed" class="{{ ($lastDbPing['failed_jobs'] ?? 0) > 0 ? 'text-danger' : '' }}">{{ $lastDbPing ? ($lastDbPing['failed_jobs'] ?? '-') : '-' }}</strong>
                    </div>
                    <div id="db-ping-result" class="mb-2" style="font-size:.72rem;color:#6b7280;min-height:16px;">
                        @if($lastDbPing)
                            @if($lastDbPing['ok']) ✅ {{ $lastDbPing['latency'] }}ms · diperbarui {{ $lastDbPing['at'] }}
                            @else ❌ {{ $lastDbPing['error'] ?? 'Gagal' }}
                            @endif
                        @endif
                    </div>
                </div>
                <button id="btn-ping-db" class="btn btn-sm btn-info w-100 ping-btn text-white" onclick="pingDb()">
                    <i class="bi bi-send me-1"></i> Test Ping
                </button>
            </div>
        </div>

        {{-- System / Supervisor --}}
        <div class="col-md-6">
            <div class="health-card h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:38px;height:38px;background:linear-gradient(135deg,#6366f1,#4f46e5);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-cpu text-white" style="font-size:1.1rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight:700;font-size:.95rem;">System & Supervisor</div>
                            <div style="font-size:.72rem;color:#6b7280;">Disk · Load · Proses</div>
                        </div>
                    </div>
                    <span id="sys-dot" class="status-dot {{ $lastSystemPing ? ($lastSystemPing['ok'] ? 'ok' : 'fail') : 'unknown' }}"></span>
                </div>
                <div style="font-size:.78rem;color:#374151;">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Disk bebas</span>
                        <strong id="sys-disk">{{ $lastSystemPing ? ($lastSystemPing['disk_free_gb'] ?? '-') . ' GB (' . (100 - ($lastSystemPing['disk_used_pct'] ?? 0)) . '% free)' : '-' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Load 1 menit</span>
                        <strong id="sys-load">{{ $lastSystemPing ? ($lastSystemPing['load_1m'] ?? '-') : '-' }}</strong>
                    </div>
                    <div id="sys-processes" class="mb-2" style="font-size:.72rem;max-height:80px;overflow-y:auto;">
                        @if(!empty($lastSystemPing['processes']))
                            @foreach($lastSystemPing['processes'] as $proc)
                                <div class="d-flex justify-content-between">
                                    <span class="text-truncate me-1" style="max-width:200px;">{{ $proc['name'] }}</span>
                                    <span class="{{ $proc['status']==='RUNNING' ? 'text-success' : 'text-danger' }} fw-semibold">{{ $proc['status'] }}</span>
                                </div>
                            @endforeach
                        @else
                            <span class="text-muted">Klik Test Ping untuk melihat proses</span>
                        @endif
                    </div>
                    <div id="sys-ping-result" style="font-size:.72rem;color:#6b7280;min-height:16px;">
                        @if($lastSystemPing)
                            @if($lastSystemPing['ok']) ✅ {{ $lastSystemPing['latency'] }}ms · diperbarui {{ $lastSystemPing['at'] }}
                            @else ❌ {{ $lastSystemPing['error'] ?? 'Gagal' }}
                            @endif
                        @endif
                    </div>
                </div>
                <button id="btn-ping-sys" class="btn btn-sm btn-secondary w-100 ping-btn mt-2" onclick="pingSystem()">
                    <i class="bi bi-send me-1"></i> Test Ping
                </button>
            </div>
        </div>
    </div>

    {{-- ── Row 1b: WA Session Status ─────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-phone me-1 text-success"></i>
                        <strong>Status Sesi WhatsApp</strong>
                        <span id="wa-session-at" class="ms-2" style="font-size:.72rem;color:#9ca3af;">
                            @if($lastWhacenterPing && !empty($lastWhacenterPing['sessions']))
                                diperbarui {{ $lastWhacenterPing['at'] ?? '' }}
                            @endif
                        </span>
                    </div>
                    <button class="btn btn-sm btn-outline-success" onclick="pingWa()" style="font-size:.75rem;">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
                <div class="card-body p-0" id="wa-sessions-wrap">
                    @php
                        $cachedSessions = $lastWhacenterPing['sessions'] ?? [];
                    @endphp
                    @if(!empty($cachedSessions))
                    <table class="table table-sm mb-0" style="font-size:.82rem;">
                        <thead style="background:#f0fdf4;">
                            <tr>
                                <th class="ps-3">Label</th>
                                <th>Phone ID</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Grup Ter-cache</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cachedSessions as $s)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $s['label'] }}</td>
                                <td class="text-muted" style="font-size:.78rem;">{{ $s['phone_id'] }}</td>
                                <td>
                                    @if($s['status'] === 'open')
                                        <span class="badge" style="background:#dcfce7;color:#16a34a;">● Terhubung</span>
                                    @elseif($s['status'] === 'qr')
                                        <span class="badge" style="background:#fef9c3;color:#a16207;">⏳ QR</span>
                                    @else
                                        <span class="badge" style="background:#fee2e2;color:#dc2626;">✕ {{ $s['status'] }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">{{ $s['groups_cached'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @else
                    <div class="text-center text-muted py-4" style="font-size:.85rem;">
                        <i class="bi bi-hourglass me-2"></i>Klik <strong>Refresh</strong> untuk melihat status sesi
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Token Usage ────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <i class="bi bi-coin me-1 text-warning"></i>
                        <strong>Penggunaan Token Gemini — 7 Hari Terakhir</strong>
                    </div>
                    <span class="cost-tag">
                        Estimasi 7 hari: Rp {{ number_format($totalCostIdr) }} / ${{ number_format($totalCostUsd, 4) }}
                    </span>
                </div>
                <div class="card-body">

                    {{-- Mini bar chart --}}
                    @php
                        $maxCalls = max(1, collect($tokenDays)->max('calls'));
                        $todayLabel = now()->format('d/m');
                    @endphp
                    <div class="token-day-bar mb-3">
                        @foreach($tokenDays as $day)
                        <div class="token-day-col" title="{{ $day['calls'] }} calls · {{ number_format($day['prompt_tokens']) }} prompt · Rp {{ number_format($day['cost_idr']) }}">
                            <div class="bar {{ $day['label'] === $todayLabel ? 'today' : '' }}"
                                 style="height:{{ max(3, round($day['calls'] / $maxCalls * 72)) }}px"></div>
                            <span class="lbl">{{ $day['label'] }}</span>
                        </div>
                        @endforeach
                    </div>

                    {{-- Table --}}
                    <table class="table table-sm mb-0" style="font-size:.8rem;">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th class="text-end">API Calls</th>
                                <th class="text-end">Gambar</th>
                                <th class="text-end">Prompt Tokens</th>
                                <th class="text-end">Output Tokens</th>
                                <th class="text-end">Total Tokens</th>
                                <th class="text-end">Est. Biaya (IDR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(array_reverse($tokenDays) as $day)
                            <tr {{ $day['label'] === $todayLabel ? 'style=background:#f0fdf8' : '' }}>
                                <td>
                                    {{ $day['date'] }}
                                    @if($day['label'] === $todayLabel)
                                        <span class="badge" style="background:#d1fae5;color:#059669;font-size:.65rem;">Hari ini</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($day['calls']) }}</td>
                                <td class="text-end">{{ number_format($day['image_calls']) }}</td>
                                <td class="text-end">{{ number_format($day['prompt_tokens']) }}</td>
                                <td class="text-end">{{ number_format($day['output_tokens']) }}</td>
                                <td class="text-end">{{ number_format($day['prompt_tokens'] + $day['output_tokens']) }}</td>
                                <td class="text-end">
                                    @if($day['cost_idr'] > 0)
                                        <span class="cost-tag">Rp {{ number_format($day['cost_idr']) }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot style="border-top:2px solid #d1fae5;">
                            <tr style="font-weight:700;">
                                <td>Total 7 Hari</td>
                                <td class="text-end">{{ number_format($totalCalls) }}</td>
                                <td class="text-end">{{ number_format($totalImageCalls) }}</td>
                                <td class="text-end">{{ number_format($totalPrompt) }}</td>
                                <td class="text-end">{{ number_format($totalOutput) }}</td>
                                <td class="text-end">{{ number_format($totalPrompt + $totalOutput) }}</td>
                                <td class="text-end">
                                    <span class="cost-tag">Rp {{ number_format($totalCostIdr) }}</span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="mt-2" style="font-size:.72rem;color:#9ca3af;">
                        * Harga referensi: Gemini Flash ${{ number_format(0.075, 3) }}/1M input tokens, ${{ number_format(0.30, 2) }}/1M output tokens.
                        Data diakumulasi sejak fitur token tracking diaktifkan.
                        Nilai historis sebelum aktivasi tidak tersedia.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 3: Per-Agent Performance ────────────────────────────────── --}}
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-robot me-1 text-success"></i>
                    <strong>Performa Agen AI — 7 Hari Terakhir</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Agen</th>
                                <th class="text-end">Total</th>
                                <th class="text-end" style="color:#22c55e;">Sukses</th>
                                <th class="text-end" style="color:#ef4444;">Gagal</th>
                                <th class="text-end" style="color:#f59e0b;">Skip</th>
                                <th style="min-width:120px;">Success Rate</th>
                                <th class="text-end">Avg ms</th>
                                <th class="text-end">Terakhir Run</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($agentStats as $row)
                            <tr class="agent-row">
                                <td>
                                    <span style="font-weight:600;font-size:.85rem;">{{ $row->agent_name }}</span>
                                </td>
                                <td class="text-end">{{ number_format($row->total) }}</td>
                                <td class="text-end" style="color:#16a34a;font-weight:600;">{{ number_format($row->success) }}</td>
                                <td class="text-end" style="color:#ef4444;font-weight:600;">{{ number_format($row->failed) }}</td>
                                <td class="text-end" style="color:#d97706;">{{ number_format($row->skipped) }}</td>
                                <td style="min-width:120px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="bar-track flex-grow-1">
                                            <div class="bar-fill {{ $row->success_rate < 70 ? 'danger' : ($row->success_rate < 90 ? 'warn' : '') }}"
                                                 style="width:{{ $row->success_rate }}%"></div>
                                        </div>
                                        <span style="font-size:.75rem;font-weight:700;min-width:32px;text-align:right;
                                            color:{{ $row->success_rate >= 90 ? '#16a34a' : ($row->success_rate >= 70 ? '#d97706' : '#ef4444') }}">
                                            {{ $row->success_rate }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end" style="font-size:.8rem;">
                                    @if($row->avg_ms)
                                        <span class="latency-badge {{ $row->avg_ms > 5000 ? 'bg-danger text-white' : ($row->avg_ms > 2000 ? 'bg-warning text-dark' : 'bg-success bg-opacity-10 text-success') }}">
                                            {{ number_format($row->avg_ms) }}ms
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end" style="font-size:.78rem;color:#6b7280;">
                                    {{ $row->last_run ? \Carbon\Carbon::parse($row->last_run)->diffForHumans() : '-' }}
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox me-2"></i>Belum ada log agen dalam 7 hari terakhir
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
function setLoading(btnId, loading) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.disabled = loading;
    if (loading) {
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menguji...';
    }
}

// Centralized POST helper. Auto-detects expired session (419 CSRF / 401 auth)
// and reloads the page — Laravel's auth middleware will then redirect to /login.
let _sessionExpiredNoticed = false;
async function pingFetch(url) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
    if (res.status === 419 || res.status === 401) {
        if (!_sessionExpiredNoticed) {
            _sessionExpiredNoticed = true;
            alert('Sesi login Anda sudah berakhir. Halaman akan di-refresh untuk login ulang.');
            window.location.reload();
        }
        throw new Error('Session expired');
    }
    return res;
}

async function pingGemini() {
    setLoading('btn-ping-gemini', true);
    document.getElementById('gemini-ping-result').textContent = '⏳ Menguji koneksi...';
    try {
        const res = await pingFetch('{{ route("ai-health.ping-gemini") }}');
        const data = await res.json();
        const dot = document.getElementById('gemini-dot');
        dot.className = 'status-dot ' + (data.ok ? 'ok' : 'fail');
        document.getElementById('gemini-last-at').textContent = data.at ?? '-';
        const listEl = document.getElementById('gemini-models-list');
        const escape = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        if (data.models && data.models.length) {
            listEl.innerHTML = data.models.map(m => {
                let statusHtml;
                if (m.ok === true) {
                    statusHtml = `<span class="text-success">✅ OK</span>` + (m.latency ? ` <span class="text-muted" style="font-size:.65rem;">${m.latency}ms</span>` : '');
                } else if (m.ok === false) {
                    statusHtml = `<span class="text-danger" title="${escape(m.error || '')}">❌</span>`;
                } else {
                    statusHtml = `<span class="text-muted" style="font-size:.65rem;">${m.configured ? 'configured' : 'not set'}</span>`;
                }
                return `<div class="d-flex justify-content-between align-items-start mb-1" style="border-bottom:1px dashed #e5e7eb;padding-bottom:3px;">
                    <div style="flex:1;min-width:0;">
                        <div><strong>${escape(m.provider)}</strong> <span class="text-muted">— ${escape(m.role)}</span></div>
                        <div class="text-muted" style="font-size:.65rem;word-break:break-all;">${escape(m.model)}</div>
                    </div>
                    <div class="text-end ms-2" style="white-space:nowrap;">${statusHtml}</div>
                </div>`;
            }).join('');
        }
        document.getElementById('btn-ping-gemini').innerHTML = '<i class="bi bi-send me-1"></i> Test Ping';
    } catch(e) {
        document.getElementById('gemini-ping-result').textContent = '❌ Request error: ' + e.message;
        document.getElementById('btn-ping-gemini').innerHTML = '<i class="bi bi-send me-1"></i> Test Ping';
    }
    document.getElementById('btn-ping-gemini').disabled = false;
}

function renderWaSessions(sessions) {
    const wrap = document.getElementById('wa-sessions-wrap');
    if (!wrap) return;
    if (!sessions || sessions.length === 0) {
        wrap.innerHTML = '<div class="text-center text-muted py-4" style="font-size:.85rem;"><i class="bi bi-exclamation-circle me-2"></i>Tidak ada sesi aktif</div>';
        return;
    }
    const rows = sessions.map(s => {
        const statusBadge = s.status === 'open'
            ? '<span class="badge" style="background:#dcfce7;color:#16a34a;">● Terhubung</span>'
            : s.status === 'qr'
            ? '<span class="badge" style="background:#fef9c3;color:#a16207;">⏳ QR</span>'
            : `<span class="badge" style="background:#fee2e2;color:#dc2626;">✕ ${s.status}</span>`;
        return `<tr><td class="ps-3 fw-semibold">${s.label}</td><td class="text-muted" style="font-size:.78rem;">${s.phone_id}</td><td>${statusBadge}</td><td class="text-end pe-3">${s.groups_cached}</td></tr>`;
    }).join('');
    wrap.innerHTML = `<table class="table table-sm mb-0" style="font-size:.82rem;">
        <thead style="background:#f0fdf4;"><tr><th class="ps-3">Label</th><th>Phone ID</th><th>Status</th><th class="text-end pe-3">Grup Ter-cache</th></tr></thead>
        <tbody>${rows}</tbody></table>`;
}

async function pingWa() {
    setLoading('btn-ping-wa', true);
    document.getElementById('wa-ping-result').textContent = '⏳ Menguji koneksi...';
    try {
        const res = await pingFetch('{{ route("ai-health.ping-whacenter") }}');
        const data = await res.json();
        const dot = document.getElementById('wa-dot');
        dot.className = 'status-dot ' + (data.ok ? 'ok' : 'fail');
        document.getElementById('wa-last-at').textContent = data.at ?? '-';
        document.getElementById('wa-ping-result').textContent = data.ok
            ? `✅ ${data.latency}ms — HTTP ${data.http} · uptime ${data.uptime ? Math.floor(data.uptime/60) + 'm' : ''}`
            : `❌ ${data.error ?? 'HTTP ' + (data.http ?? '?')}`;
        document.getElementById('btn-ping-wa').innerHTML = '<i class="bi bi-send me-1"></i> Test Ping';
        // Update session table and refresh timestamp
        if (data.sessions) renderWaSessions(data.sessions);
        const atEl = document.getElementById('wa-session-at');
        if (atEl && data.at) atEl.textContent = 'diperbarui ' + data.at;
    } catch(e) {
        document.getElementById('wa-ping-result').textContent = '❌ Request error: ' + e.message;
        document.getElementById('btn-ping-wa').innerHTML = '<i class="bi bi-send me-1"></i> Test Ping';
    }
    document.getElementById('btn-ping-wa').disabled = false;
}

// Auto-refresh WA sessions on page load and every 60s
document.addEventListener('DOMContentLoaded', () => {
    pingWa();
    pingQueue();
    pingDb();
    pingSystem();
    setInterval(pingWa, 60000);
    setInterval(pingQueue, 60000);
});

async function pingQueue() {
    setLoading('btn-ping-queue', true);
    document.getElementById('queue-ping-result').textContent = '⏳ Mengecek queue...';
    try {
        const res = await pingFetch('{{ route("ai-health.ping-queue") }}');
        const data = await res.json();
        document.getElementById('queue-dot').className = 'status-dot ' + (data.ok ? 'ok' : 'fail');
        document.getElementById('queue-pending').textContent = data.pending ?? '-';
        const failedEl = document.getElementById('queue-failed');
        const recent = data.recent_failed ?? 0;
        failedEl.textContent = (data.failed ?? '-') + (recent > 0 ? ` (${recent} dlm 1 jam)` : '');
        failedEl.className = recent > 0 ? 'text-danger' : ((data.failed ?? 0) > 0 ? 'text-muted' : '');
        document.getElementById('queue-success').textContent = data.success_last_1h ?? '-';
        document.getElementById('queue-ping-result').textContent = data.ok
            ? `✅ OK · ${data.latency}ms`
            : `❌ ${data.error ?? 'Ada job failed/stuck'}`;
    } catch(e) {
        document.getElementById('queue-ping-result').textContent = '❌ ' + e.message;
    }
    document.getElementById('btn-ping-queue').disabled = false;
    document.getElementById('btn-ping-queue').innerHTML = '<i class="bi bi-send me-1"></i> Test Ping';
}

async function pingDb() {
    setLoading('btn-ping-db', true);
    document.getElementById('db-ping-result').textContent = '⏳ Mengecek database...';
    try {
        const res = await pingFetch('{{ route("ai-health.ping-database") }}');
        const data = await res.json();
        document.getElementById('db-dot').className = 'status-dot ' + (data.ok ? 'ok' : 'fail');
        if (data.version) document.getElementById('db-version').textContent = data.version.substring(0, 45);
        document.getElementById('db-size').textContent = data.size_mb != null ? data.size_mb + ' MB' : '-';
        document.getElementById('db-pending').textContent = data.pending_jobs ?? '-';
        const failedEl = document.getElementById('db-failed');
        failedEl.textContent = data.failed_jobs ?? '-';
        failedEl.className = (data.failed_jobs > 0) ? 'text-danger' : '';
        document.getElementById('db-ping-result').textContent = data.ok
            ? `✅ ${data.latency}ms · diperbarui ${data.at}`
            : `❌ ${data.error ?? 'Gagal'}`;
    } catch(e) {
        document.getElementById('db-ping-result').textContent = '❌ ' + e.message;
    }
    document.getElementById('btn-ping-db').disabled = false;
    document.getElementById('btn-ping-db').innerHTML = '<i class="bi bi-send me-1"></i> Test Ping';
}

async function pingSystem() {
    setLoading('btn-ping-sys', true);
    document.getElementById('sys-ping-result').textContent = '⏳ Mengecek sistem...';
    try {
        const res = await pingFetch('{{ route("ai-health.ping-system") }}');
        const data = await res.json();
        document.getElementById('sys-dot').className = 'status-dot ' + (data.ok ? 'ok' : 'fail');
        document.getElementById('sys-disk').textContent = data.disk_free_gb != null
            ? `${data.disk_free_gb} GB (${100 - data.disk_used_pct}% free)` : '-';
        document.getElementById('sys-load').textContent = data.load_1m ?? '-';
        // Render process list
        const procDiv = document.getElementById('sys-processes');
        if (data.processes && data.processes.length > 0) {
            procDiv.innerHTML = data.processes.map(p =>
                `<div class="d-flex justify-content-between">
                    <span class="text-truncate me-1" style="max-width:200px;">${p.name}</span>
                    <span class="${p.status === 'RUNNING' ? 'text-success' : 'text-danger'} fw-semibold">${p.status}</span>
                </div>`
            ).join('');
        } else {
            procDiv.textContent = data.error ? 'supervisorctl tidak tersedia' : 'Tidak ada proses ditemukan';
        }
        document.getElementById('sys-ping-result').textContent = data.ok
            ? `✅ ${data.latency}ms · diperbarui ${data.at}`
            : `❌ ${data.error ?? 'Ada proses tidak jalan'}`;
    } catch(e) {
        document.getElementById('sys-ping-result').textContent = '❌ ' + e.message;
    }
    document.getElementById('btn-ping-sys').disabled = false;
    document.getElementById('btn-ping-sys').innerHTML = '<i class="bi bi-send me-1"></i> Test Ping';
}
</script>
@endpush
