@extends('layouts.app')
@section('title', 'Detail Kontak')
@section('breadcrumb', 'Detail Kontak')

@section('content')
<div class="page-header">
    <div class="d-flex align-items-center gap-3">
        <a href="{{ route('contacts.index') }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h1><i class="bi bi-person-circle me-2" style="color:#f472b6;"></i>{{ $contact->name ?: $contact->phone_number }}</h1>
            <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">{{ $contact->phone_number }}</p>
        </div>
    </div>
</div>

<div class="page-body">
    <div class="row g-3">
        <!-- Stats -->
        <div class="col-12 col-md-4">
            <div class="card mb-3">
                <div class="card-body text-center py-4">
                    <div style="width:64px;height:64px;border-radius:50%;background:#fce7f3;display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
                        <i class="bi bi-person-fill" style="font-size:1.8rem;color:#be185d;"></i>
                    </div>
                    <div style="font-weight:700;color:#111827;font-size:1.1rem;">{{ $contact->name ?: '-' }}</div>
                    <div style="color:#6b7280;font-size:.82rem;margin-top:.2rem;">{{ $contact->phone_number }}</div>
                    <a href="https://wa.me/{{ $contact->phone_number }}" target="_blank" class="btn btn-sm mt-3" style="background:#dcfce7;border:1px solid #bbf7d0;color:#15803d;">
                        <i class="bi bi-whatsapp me-2"></i>Chat di WhatsApp
                    </a>
                </div>
                <div class="card-footer" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:.75rem;">
                    <div class="row text-center">
                        <div class="col-6" style="border-right:1px solid #e5e7eb;">
                            <div style="font-weight:700;font-size:1.2rem;color:#4f46e5;">{{ number_format($contact->message_count) }}</div>
                            <div style="font-size:.72rem;color:#6b7280;">Pesan</div>
                        </div>
                        <div class="col-6">
                            <div style="font-weight:700;font-size:1.2rem;color:#92400e;">{{ number_format($contact->ad_count) }}</div>
                            <div style="font-size:.72rem;color:#6b7280;">Iklan</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;">Info</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Pertama Terlihat</div>
                        <div style="color:#111827;font-size:.875rem;margin-top:.2rem;">{{ $contact->created_at->format('d M Y') }}</div>
                    </div>
                    <div>
                        <div style="font-size:.72rem;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;">Terakhir Aktif</div>
                        <div style="color:#111827;font-size:.875rem;margin-top:.2rem;">{{ $contact->last_seen ? $contact->last_seen->format('d M Y H:i') : '-' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: messages + listings -->
        <div class="col-12 col-md-8">
            <!-- Listings -->
            @if($listings->count())
            <div class="card mb-3">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-tag me-2" style="color:#15803d;"></i>Iklan ({{ $listings->count() }})</span>
                </div>
                <div class="card-body p-0">
                    @foreach($listings as $listing)
                    <div class="d-flex justify-content-between align-items-start p-3 {{ !$loop->last ? 'border-bottom' : '' }}" style="border-color:#e5e7eb;">
                        <div>
                            <div style="font-weight:600;color:#111827;font-size:.875rem;">{{ $listing->title }}</div>
                            <div style="color:#15803d;font-size:.85rem;">{{ $listing->price_formatted }}</div>
                            <div style="font-size:.75rem;color:#6b7280;margin-top:.2rem;">{{ $listing->created_at->format('d M Y') }}</div>
                        </div>
                        <a href="{{ route('listings.show', $listing) }}" class="btn btn-sm" style="background:#eef2ff;border:none;color:#4f46e5;padding:.2rem .6rem;"><i class="bi bi-eye"></i></a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Recent Messages -->
            <div class="card">
                <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
                    <span style="font-weight:600;color:#111827;font-size:.9rem;"><i class="bi bi-chat-dots me-2" style="color:#4f46e5;"></i>Pesan Terbaru</span>
                </div>
                <div class="card-body p-0">
                    @forelse($messages as $msg)
                    <div class="d-flex gap-3 p-3 {{ !$loop->last ? 'border-bottom' : '' }}" style="border-color:#e5e7eb;">
                        <div style="flex:1;min-width:0;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="badge" style="background:#eef2ff;color:#4f46e5;font-size:.7rem;">{{ $msg->group->group_name ?? '-' }}</span>
                                <span style="font-size:.72rem;color:#6b7280;">{{ $msg->created_at->format('d/m H:i') }}</span>
                            </div>
                            <p style="font-size:.82rem;color:#374151;margin:0;">{{ Str::limit($msg->raw_body, 100) }}</p>
                        </div>
                        @if($msg->is_ad)
                        <span class="badge align-self-start" style="background:#fef9c3;color:#92400e;height:fit-content;">Ad</span>
                        @endif
                    </div>
                    @empty
                    <div class="text-center py-4" style="color:#9ca3af;font-size:.875rem;">Belum ada pesan</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
