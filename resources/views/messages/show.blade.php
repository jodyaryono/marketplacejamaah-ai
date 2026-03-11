@extends('layouts.app')
@section('title', 'Detail Pesan')
@section('breadcrumb', 'Detail Pesan')

@section('content')
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('messages.index') }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1><i class="bi bi-chat-text me-2" style="color:#818cf8;"></i>Detail Pesan</h1>
            <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">{{ $message->created_at->format('d M Y H:i') }}</p>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="row g-3">
        <!-- Main -->
        <div class="col-12 col-lg-8">
            <!-- Message Card -->
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:1rem 1.25rem;">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;">
                            <i class="bi bi-person" style="color:#4f46e5;"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;color:#111827;">{{ $message->sender_name ?: $message->sender_number }}</div>
                            <div style="font-size:.78rem;color:#6b7280;">{{ $message->sender_number }}</div>
                        </div>
                        <div class="ms-auto">
                            @if($message->is_ad === true)
                                <span class="badge" style="background:#fef9c3;color:#92400e;"><i class="bi bi-megaphone me-1"></i>Iklan {{ $message->ad_confidence ? '('.round($message->ad_confidence*100).'%)' : '' }}</span>
                            @elseif($message->is_ad === false)
                                <span class="badge" style="background:#f3f4f6;color:#6b7280;">Bukan Iklan</span>
                            @else
                                <span class="badge" style="background:#fee2e2;color:#b91c1c;">Belum Diproses</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($message->media_url)
                    <div class="mb-3 p-3 rounded" style="background:#f8fafc;">
                        @php $ext = strtolower(pathinfo($message->media_url, PATHINFO_EXTENSION)); @endphp
                        @if(in_array($ext, ['jpg','jpeg','png','gif','webp']))
                            <img src="{{ $message->media_url }}" class="img-fluid rounded" style="max-height:300px;">
                        @else
                                <a href="{{ $message->media_url }}" target="_blank" class="d-flex align-items-center gap-2" style="color:#4f46e5;text-decoration:none;">
                                <i class="bi bi-paperclip" style="font-size:1.2rem;"></i>
                                <span>Lihat Media</span>
                            </a>
                        @endif
                    </div>
                    @endif
                    <p style="color:#374151;white-space:pre-wrap;line-height:1.7;">{{ $message->raw_body ?: '(tidak ada teks)' }}</p>
                </div>
            </div>

            <!-- Linked Listing -->
            @if($message->listing)
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-tag me-2" style="color:#15803d;"></i>Listing Terkait</span>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div style="font-weight:600;color:#111827;">{{ $message->listing->title }}</div>
                            <div style="color:#15803d;font-size:.9rem;margin-top:.25rem;">{{ $message->listing->price_formatted }}</div>
                            <div style="font-size:.8rem;color:#6b7280;margin-top:.25rem;">
                                <span class="badge" style="background:#eef2ff;color:#4f46e5;">{{ $message->listing->category->name ?? '-' }}</span>
                            </div>
                        </div>
                        <a href="{{ route('listings.show', $message->listing) }}" class="btn btn-sm" style="background:#eef2ff;border:none;color:#4f46e5;">Detail</a>
                    </div>
                </div>
            </div>
            @endif

            <!-- Raw Payload -->
            @if($message->raw_payload)
            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-code-slash me-2" style="color:#6b7280;"></i>Raw Payload</span>
                </div>
                <div class="card-body p-0">
                    <pre style="background:#f8fafc;color:#374151;padding:1rem;margin:0;font-size:.75rem;border-radius:0 0 12px 12px;overflow-x:auto;max-height:250px;">{{ json_encode($message->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-12 col-lg-4">
            <!-- Meta Info -->
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;">Informasi</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Grup</div>
                        <div style="color:#111827;font-size:.875rem;margin-top:.2rem;">{{ $message->group->group_name ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Tipe</div>
                        <div style="color:#111827;font-size:.875rem;margin-top:.2rem;">{{ ucfirst($message->message_type) }}</div>
                    </div>
                    <div class="mb-3">
                        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">ID Pesan WA</div>
                        <div style="color:#6b7280;font-size:.8rem;font-family:monospace;margin-top:.2rem;">{{ $message->message_id }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Diterima</div>
                        <div style="color:#111827;font-size:.875rem;margin-top:.2rem;">{{ $message->created_at->format('d M Y, H:i:s') }}</div>
                    </div>
                </div>
            </div>

            <!-- Agent Logs -->
            @if($agentLogs->count())
            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-robot me-2" style="color:#6d28d9;"></i>Agent Logs</span>
                </div>
                <div class="card-body px-3 py-2">
                    @foreach($agentLogs as $log)
                    <div class="d-flex gap-3 py-2" style="{{ !$loop->last ? 'border-bottom:1px solid #e5e7eb;' : '' }}">
                        <div style="width:6px;height:6px;border-radius:50%;margin-top:.45rem;flex-shrink:0;background:{{ $log->status === 'success' ? '#4ade80' : ($log->status === 'failed' ? '#f87171' : '#fbbf24') }};"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:.78rem;font-weight:600;color:#111827;">{{ $log->agent_name }}</div>
                            <div class="d-flex justify-content-between mt-1">
                                <span style="font-size:.72rem;color:{{ $log->status === 'success' ? '#15803d' : ($log->status === 'failed' ? '#b91c1c' : '#92400e') }};">{{ ucfirst($log->status) }}</span>
                                @if($log->duration_ms)
                                <span style="font-size:.72rem;color:#6b7280;">{{ $log->duration_ms }}ms</span>
                                @endif
                            </div>
                            @if($log->error)
                            <div style="font-size:.72rem;color:#dc2626;margin-top:.2rem;">{{ Str::limit($log->error, 60) }}</div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
