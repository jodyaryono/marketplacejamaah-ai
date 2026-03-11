@extends('layouts.app')
@section('title', 'Agent Logs')
@section('breadcrumb', 'Agent Logs')

@section('content')
<div class="page-header">
    <h1><i class="bi bi-robot me-2" style="color:#059669;"></i>Agent Logs</h1>
    <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Monitor aktivitas AI agent pipeline</p>
</div>

<div class="page-body">
    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-list-check"></i></div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total Eksekusi</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-check-circle"></i></div>
                <div class="stat-value">{{ number_format($stats['success']) }}</div>
                <div class="stat-label">Sukses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-x-circle"></i></div>
                <div class="stat-value">{{ number_format($stats['failed']) }}</div>
                <div class="stat-label">Gagal</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-speedometer2"></i></div>
                <div class="stat-value">{{ $stats['avg_ms'] ? round($stats['avg_ms']).'ms' : '-' }}</div>
                <div class="stat-label">Rata-rata Waktu</div>
            </div>
        </div>
    </div>

    <!-- Health Check -->
    <div class="mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <h6 style="margin:0;color:#374151;font-weight:700;font-size:.9rem;">🩺 Health Check Agent Pipeline</h6>
            <span style="font-size:.72rem;color:#9ca3af;">— status 24 jam terakhir</span>
        </div>
        @if(session('prompt_saved'))
        <div class="alert alert-success py-2 mb-3" style="font-size:.82rem;background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;">
            <i class="bi bi-check-circle me-1"></i>{{ session('prompt_saved') }}
        </div>
        @endif
        <div class="row g-2">
            @foreach($healthCheck as $name => $h)
            @php
                $dot = match($h['last_status']) {
                    'success' => '#4ade80',
                    'failed'  => '#f87171',
                    default   => ($h['last_status'] ? '#fbbf24' : '#6b7280'),
                };
                $rate = $h['total_24h'] > 0 ? round($h['success_24h'] / $h['total_24h'] * 100) : null;
                $rateColor = $rate === null ? '#9ca3af' : ($rate >= 90 ? '#16a34a' : ($rate >= 60 ? '#d97706' : '#dc2626'));
                $hasPrompts = isset($agentPrompts[$name]);
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100" style="border:1px solid #e5e7eb;">
                    <div class="card-body py-3 px-3">
                        <div class="d-flex align-items-start gap-2">
                            <div style="width:34px;height:34px;border-radius:8px;background:{{ $h['info']['color'] }}1a;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="bi {{ $h['info']['icon'] }}" style="color:{{ $h['info']['color'] }};font-size:1rem;"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <div style="width:7px;height:7px;border-radius:50%;background:{{ $dot }};flex-shrink:0;"></div>
                                    <span style="font-weight:700;font-size:.82rem;color:#111827;">{{ $name }}</span>
                                </div>
                                <p style="font-size:.72rem;color:#6b7280;margin:0 0 .6rem 0;line-height:1.4;">{{ $h['info']['desc'] }}</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <span style="font-size:.7rem;background:#f3f4f6;color:#374151;padding:.15rem .5rem;border-radius:20px;">
                                        🕐 {{ $h['last_run'] ? \Carbon\Carbon::parse($h['last_run'])->diffForHumans() : 'Belum pernah' }}
                                    </span>
                                    @if($h['total_24h'] > 0)
                                    <span style="font-size:.7rem;background:#f3f4f6;color:{{ $rateColor }};padding:.15rem .5rem;border-radius:20px;font-weight:600;">
                                        ✓ {{ $rate }}% ({{ $h['success_24h'] }}/{{ $h['total_24h'] }})
                                    </span>
                                    @if($h['avg_ms'])
                                    <span style="font-size:.7rem;background:#f3f4f6;color:#6b7280;padding:.15rem .5rem;border-radius:20px;">
                                        ⚡ {{ number_format($h['avg_ms']) }}ms avg
                                    </span>
                                    @endif
                                    @else
                                    <span style="font-size:.7rem;background:#f3f4f6;color:#9ca3af;padding:.15rem .5rem;border-radius:20px;">
                                        Tidak ada aktivitas
                                    </span>
                                    @endif
                                    @if($hasPrompts)
                                    <button class="btn btn-sm py-0 px-2" style="font-size:.7rem;background:#7c3aed18;color:#7c3aed;border:1px solid #7c3aed40;border-radius:20px;"
                                        data-bs-toggle="modal" data-bs-target="#promptModal_{{ $name }}">
                                        <i class="bi bi-pencil-square me-1"></i>Edit Prompt
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-3 px-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-6 col-md-3">
                    <select name="agent_name" class="form-select form-select-sm">
                        <option value="">Semua Agent</option>
                        @foreach($agentNames as $agentName)
                            <option value="{{ $agentName }}" {{ request('agent_name') == $agentName ? 'selected' : '' }}>{{ $agentName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="success" {{ request('status') == 'success' ? 'selected' : '' }}>Sukses</option>
                        <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Gagal</option>
                        <option value="running" {{ request('status') == 'running' ? 'selected' : '' }}>Running</option>
                    </select>
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="{{ route('agents.index') }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="px-4">Agent</th>
                            <th>Status</th>
                            <th>Durasi</th>
                            <th>Error</th>
                            <th>Waktu</th>
                            <th class="px-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        @php
                            $ai = $agentInfo[$log->agent_name] ?? ['color'=>'#6b7280','icon'=>'bi-robot','desc'=>''];
                            $ac = $ai['color'];
                            $sc = ['success'=>['bg'=>'#dcfce7','c'=>'#15803d','dot'=>'#4ade80'],
                                   'failed' =>['bg'=>'#fee2e2','c'=>'#b91c1c','dot'=>'#f87171'],
                                   'running'=>['bg'=>'#fef9c3','c'=>'#92400e','dot'=>'#fbbf24']];
                            $s = $sc[$log->status] ?? $sc['running'];
                        @endphp
                        <tr style="border-left:3px solid {{ $ac }};">
                            <td class="px-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:36px;height:36px;border-radius:10px;background:{{ $ac }}18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi {{ $ai['icon'] }}" style="color:{{ $ac }};font-size:1rem;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;font-size:.85rem;" class="d-flex align-items-center gap-2">
                                            <span style="color:{{ $ac }};">{{ $log->agent_name }}</span>
                                        </div>
                                        @if($ai['desc'])
                                        <div style="font-size:.7rem;color:#9ca3af;margin-top:.1rem;max-width:320px;line-height:1.3;">{{ Str::limit($ai['desc'], 80) }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge d-flex align-items-center gap-1" style="background:{{ $s['bg'] }};color:{{ $s['c'] }};width:fit-content;">
                                    <span style="width:6px;height:6px;border-radius:50%;background:{{ $s['dot'] }};display:inline-block;"></span>
                                    {{ ucfirst($log->status) }}
                                </span>
                            </td>
                            <td>
                                @if($log->duration_ms)
                                    <span style="font-size:.82rem;font-weight:600;color:{{ $log->duration_ms > 5000 ? '#d97706' : ($log->duration_ms > 1000 ? '#6b7280' : '#059669') }};">
                                        {{ number_format($log->duration_ms) }}ms
                                    </span>
                                @else
                                    <span style="color:#9ca3af;">-</span>
                                @endif
                            </td>
                            <td style="max-width:220px;">
                                @if($log->error)
                                    <span style="font-size:.75rem;color:#dc2626;">{{ Str::limit($log->error, 50) }}</span>
                                @else
                                    <span style="color:#9ca3af;font-size:.8rem;">-</span>
                                @endif
                            </td>
                            <td style="font-size:.78rem;color:#6b7280;white-space:nowrap;">{{ $log->created_at->format('d/m H:i:s') }}</td>
                            <td class="px-4">
                                <button class="btn btn-sm" style="background:{{ $ac }}18;border:none;color:{{ $ac }};padding:.25rem .6rem;border-radius:7px;"
                                    data-bs-toggle="modal" data-bs-target="#logDetail{{ $log->id }}"><i class="bi bi-eye"></i></button>
                            </td>
                        </tr>

                        <!-- Log detail modal -->
                        <div class="modal fade" id="logDetail{{ $log->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content" style="border-top:4px solid {{ $ac }};border-radius:12px;">
                                    <div class="modal-header" style="background:{{ $ac }}10;">
                                        <h6 class="modal-title d-flex align-items-center gap-2">
                                            <i class="bi {{ $ai['icon'] }}" style="color:{{ $ac }};"></i>
                                            {{ $log->agent_name }} — Log Detail
                                        </h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;margin-bottom:.4rem;">Input</div>
                                            <pre style="background:#f8fafc;color:#374151;padding:.75rem;border-radius:8px;font-size:.72rem;max-height:180px;overflow:auto;margin:0;">{{ json_encode($log->input_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        <div>
                                            <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;margin-bottom:.4rem;">Output</div>
                                            <pre style="background:#f8fafc;color:#374151;padding:.75rem;border-radius:8px;font-size:.72rem;max-height:180px;overflow:auto;margin:0;">{{ json_encode($log->output_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        @if($log->error)
                                        <div class="mt-3">
                                            <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;margin-bottom:.4rem;">Error</div>
                                            <div style="background:#fee2e2;border:1px solid #fca5a5;padding:.75rem;border-radius:8px;font-size:.8rem;color:#dc2626;">{{ $log->error }}</div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-5" style="color:#9ca3af;">
                                <i class="bi bi-robot" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                                Belum ada log
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:.75rem 1rem;">
            <span style="font-size:.8rem;color:#6b7280;">{{ $logs->firstItem() }}–{{ $logs->lastItem() }} dari {{ $logs->total() }}</span>
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>

{{-- Prompt Edit Modals --}}
@foreach($agentPrompts as $agentName => $keys)
@php $ai = $agentInfo[$agentName] ?? ['color'=>'#6b7280','icon'=>'bi-robot']; @endphp
<div class="modal fade" id="promptModal_{{ $agentName }}" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-top:4px solid {{ $ai['color'] }};border-radius:12px;">
            <form method="POST" action="{{ route('agents.update-prompts') }}">
                @csrf
                <div class="modal-header" style="background:{{ $ai['color'] }}10;">
                    <h6 class="modal-title d-flex align-items-center gap-2">
                        <i class="bi {{ $ai['icon'] }}" style="color:{{ $ai['color'] }};"></i>
                        {{ $agentName }} — Edit Prompt
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="max-height:60vh;overflow-y:auto;">
                    @foreach($keys as $key)
                    @php $setting = $prompts[$key] ?? null; @endphp
                    @if($setting)
                    <div class="mb-4">
                        <label class="form-label" style="font-size:.82rem;font-weight:700;color:#374151;">
                            {{ $setting->label }}
                        </label>
                        @if($setting->description)
                        <div style="font-size:.72rem;color:#6b7280;margin-bottom:.4rem;">{{ $setting->description }}</div>
                        @endif
                        <textarea name="prompt[{{ $key }}]" class="form-control" rows="8" style="font-size:.78rem;font-family:monospace;line-height:1.5;">{{ $setting->value }}</textarea>
                    </div>
                    @endif
                    @endforeach
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;flex-shrink:0;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:{{ $ai['color'] }};color:#fff;border:none;">
                        <i class="bi bi-save me-1"></i>Simpan Prompt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
