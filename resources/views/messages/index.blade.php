@extends('layouts.app')
@section('title', 'Semua Pesan')
@section('breadcrumb', 'Semua Pesan')

@section('content')
<div class="page-header">
    <h1><i class="bi bi-chat-dots me-2" style="color:#6366f1;"></i>Semua Pesan</h1>
    <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Pesan masuk dari grup WhatsApp &amp; pesan keluar dari bot</p>
</div>

<div class="page-body">
    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-chat-dots"></i></div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total Pesan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value">{{ number_format($stats['today']) }}</div>
                <div class="stat-label">Hari Ini</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-megaphone"></i></div>
                <div class="stat-value">{{ number_format($stats['ads']) }}</div>
                <div class="stat-label">Terdeteksi Iklan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-value">{{ number_format($stats['unprocessed']) }}</div>
                <div class="stat-label">Belum Diproses</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card" style="background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;">
                <div class="stat-icon mb-2"><i class="bi bi-send"></i></div>
                <div class="stat-value">{{ number_format($stats['outgoing']) }}</div>
                <div class="stat-label">Pesan Keluar Hari Ini</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-3 px-4">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari pesan..." value="{{ request('search') }}">
                </div>
                <div class="col-6 col-md-2">
                    <select name="group_id" class="form-select form-select-sm">
                        <option value="">Semua Grup</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}" {{ request('group_id') == $g->id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Semua Tipe</option>
                        <option value="text" {{ request('type') == 'text' ? 'selected' : '' }}>Teks</option>
                        <option value="image" {{ request('type') == 'image' ? 'selected' : '' }}>Gambar</option>
                        <option value="document" {{ request('type') == 'document' ? 'selected' : '' }}>Dokumen</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="is_ad" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="1" {{ request('is_ad') === '1' ? 'selected' : '' }}>Iklan</option>
                        <option value="0" {{ request('is_ad') === '0' ? 'selected' : '' }}>Bukan Iklan</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="direction" class="form-select form-select-sm">
                        <option value="">Semua Arah</option>
                        <option value="in" {{ request('direction') === 'in' ? 'selected' : '' }}>&#8595; Masuk</option>
                        <option value="out" {{ request('direction') === 'out' ? 'selected' : '' }}>&#8593; Keluar</option>
                    </select>
                </div>
                <div class="col-6 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill">Filter</button>
                    <a href="{{ route('messages.index') }}" class="btn btn-sm flex-fill" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Reset</a>
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
                            <th class="px-4">Pengirim</th>
                            <th>Grup</th>
                            <th>Pesan</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th>Waktu</th>
                            <th class="px-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($messages as $msg)
                        <tr>
                            <td class="px-4">
                                @if(($msg->direction ?? 'in') === 'out')
                                    @php
                                        $rc = $msg->recipientContact;
                                        $rcName = $rc && $rc->name ? $rc->name : ($msg->recipient_number ?: '-');
                                        $rcAvatar = $rc->avatar ?? null;
                                        $rcInitial = mb_strtoupper(mb_substr($rcName, 0, 1));
                                    @endphp
                                    <div class="d-flex align-items-center gap-2">
                                        @if($rcAvatar)
                                            <img src="{{ $rcAvatar }}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                        @else
                                            <div style="width:32px;height:32px;border-radius:50%;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;"><i class="bi bi-robot" style="font-size:.7rem;"></i></div>
                                        @endif
                                        <div>
                                            <div style="font-size:.85rem;font-weight:600;color:#16a34a;">Jamaah Bot</div>
                                            <div style="font-size:.7rem;color:#6b7280;">→ {{ $rcName }}</div>
                                        </div>
                                    </div>
                                @else
                                    @php
                                        $sc = $msg->senderContact;
                                        $displayName = $sc && $sc->name ? $sc->name : ($msg->sender_name ?: '-');
                                        $scAvatar = $sc->avatar ?? null;
                                        $scInitial = mb_strtoupper(mb_substr($displayName !== '-' ? $displayName : '?', 0, 1));
                                    @endphp
                                    <div class="d-flex align-items-center gap-2">
                                        @if($scAvatar)
                                            <img src="{{ $scAvatar }}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                                        @else
                                            <div style="width:32px;height:32px;border-radius:50%;background:#6366f1;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;flex-shrink:0;">{{ $scInitial }}</div>
                                        @endif
                                        <div>
                                            <div style="font-size:.85rem;font-weight:600;color:#111827;">{{ $displayName }}</div>
                                            <div style="font-size:.7rem;color:#9ca3af;">{{ $msg->sender_number }}</div>
                                            @if($sc && $sc->name && $msg->sender_name && $sc->name !== $msg->sender_name)
                                                <div style="font-size:.65rem;color:#d1d5db;font-style:italic;">wa: {{ $msg->sender_name }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($msg->group)
                                    <span class="badge" style="background:#eef2ff;color:#4f46e5;font-size:.72rem;">{{ Str::limit($msg->group->group_name, 20) }}</span>
                                @else
                                    <span style="color:#9ca3af;font-size:.8rem;">-</span>
                                @endif
                            </td>
                            <td style="max-width:360px;">
                                @if($msg->media_url)
                                    @php $ext = strtolower(pathinfo($msg->media_url, PATHINFO_EXTENSION)); @endphp
                                    @if(in_array($ext, ['jpg','jpeg','png','gif','webp']))
                                        <a href="#" class="msg-thumb d-inline-block mb-1" data-full="{{ $msg->media_url }}">
                                            <img src="{{ $msg->media_url }}" alt="media" style="max-height:64px;max-width:64px;border-radius:.4rem;object-fit:cover;border:1px solid #e5e7eb;vertical-align:middle;cursor:pointer;" loading="lazy">
                                        </a><br>
                                    @elseif(in_array($ext, ['mp4','webm','mov']))
                                        <a href="{{ route('messages.show', $msg) }}" class="d-inline-flex align-items-center gap-1 mb-1" style="font-size:.75rem;color:#4f46e5;text-decoration:none;">
                                            <i class="bi bi-play-circle-fill" style="font-size:1rem;"></i> Video
                                        </a><br>
                                    @else
                                        <a href="{{ route('messages.show', $msg) }}" class="d-inline-flex align-items-center gap-1 mb-1" style="font-size:.75rem;color:#4f46e5;text-decoration:none;">
                                            <i class="bi bi-paperclip" style="font-size:.85rem;"></i> Media
                                        </a><br>
                                    @endif
                                @endif
                                <span style="font-size:.825rem;color:#374151;">{{ Str::limit($msg->raw_body, 90) }}</span>
                            </td>
                            <td>
                                @php $icons = ['text'=>'bi-text-left','image'=>'bi-image','document'=>'bi-file-earmark','video'=>'bi-camera-video','audio'=>'bi-mic'] @endphp
                                @if(($msg->direction ?? 'in') === 'out')
                                    <span style="font-size:.72rem;background:#dcfce7;color:#16a34a;padding:.2rem .5rem;border-radius:.3rem;font-weight:600;"><i class="bi bi-send me-1"></i>Keluar</span>
                                @else
                                    <i class="{{ $icons[$msg->message_type] ?? 'bi-three-dots' }}" style="color:#6b7280;"></i>
                                    <span style="font-size:.75rem;color:#6b7280;">{{ ucfirst($msg->message_type) }}</span>
                                @endif
                            </td>
                            <td>
                                @if($msg->is_ad === true)
                                    <span class="badge" style="background:#fef9c3;color:#92400e;">
                                        <i class="bi bi-megaphone me-1"></i>Iklan
                                        @if($msg->ad_confidence) ({{ round($msg->ad_confidence * 100) }}%) @endif
                                    </span>
                                @elseif($msg->is_ad === false)
                                    <span class="badge" style="background:#f1f5f9;color:#475569;">Bukan Iklan</span>
                                @elseif($msg->message_category === 'pending_onboarding')
                                    <span class="badge" style="background:#fef3c7;color:#b45309;" title="Pengirim belum menyelesaikan registrasi">
                                        <i class="bi bi-person-plus me-1"></i>Belum Daftar
                                    </span>
                                @elseif(!$msg->is_processed)
                                    <span class="badge" style="background:#e0e7ff;color:#4338ca;">
                                        <i class="bi bi-hourglass-split me-1"></i>Diproses
                                    </span>
                                @else
                                    <span class="badge" style="background:#dcfce7;color:#15803d;">
                                        <i class="bi bi-check-circle me-1"></i>Selesai
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span style="font-size:.78rem;color:#6b7280;">{{ $msg->created_at->format('d/m H:i') }}</span>
                            </td>
                            <td class="px-4">
                                <div class="d-flex gap-1">
                                    <a href="{{ route('messages.show', $msg) }}" class="btn btn-sm" style="background:#eef2ff;border:none;color:#4f46e5;padding:.2rem .6rem;" title="Lihat detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    @php
                                        $chatPhone = ($msg->direction ?? 'in') === 'out' ? $msg->recipient_number : $msg->sender_number;
                                        $chatContact = ($msg->direction ?? 'in') === 'out' ? $msg->recipientContact : $msg->senderContact;
                                        $chatName = $chatContact && $chatContact->name ? $chatContact->name : (($msg->direction ?? 'in') === 'out' ? ($msg->recipient_number ?: '-') : ($msg->sender_name ?: $msg->sender_number));
                                    @endphp
                                    @if($chatPhone)
                                    <button type="button" class="btn btn-sm btn-send-dm" style="background:#dcfce7;border:none;color:#16a34a;padding:.2rem .6rem;" title="Kirim pesan ke {{ $chatPhone }}" data-phone="{{ $chatPhone }}" data-name="{{ $chatName }}">
                                        <i class="bi bi-chat-dots"></i>
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5" style="color:#9ca3af;">
                                <i class="bi bi-chat-dots" style="font-size:2rem;display:block;margin-bottom:.5rem;color:#d1d5db;"></i>
                                Belum ada pesan
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($messages->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:.75rem 1rem;">
            <span style="font-size:.8rem;color:#6b7280;">{{ $messages->firstItem() }}–{{ $messages->lastItem() }} dari {{ $messages->total() }}</span>
            {{ $messages->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>

<!-- Send DM Modal -->
<div class="modal fade" id="sendDmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:.75rem;border:none;box-shadow:0 20px 60px rgba(0,0,0,.15);">
            <form method="POST" action="{{ route('messages.send-dm') }}">
                @csrf
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;padding:1rem 1.5rem;">
                    <h5 class="modal-title" style="font-size:1rem;font-weight:600;"><i class="bi bi-chat-dots me-2" style="color:#16a34a;"></i>Kirim Pesan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:1.5rem;">
                    <div class="mb-3">
                        <label class="form-label" style="font-size:.85rem;font-weight:500;">Kepada</label>
                        <div class="d-flex align-items-center gap-2">
                            <input type="text" name="phone" id="dmPhone" class="form-control form-control-sm" readonly style="background:#f9fafb;max-width:180px;">
                            <span id="dmName" style="font-size:.85rem;color:#6b7280;"></span>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" style="font-size:.85rem;font-weight:500;">Pesan</label>
                        <textarea name="message" id="dmMessage" class="form-control" rows="4" placeholder="Tulis pesan untuk dikirim via WhatsApp..." required maxlength="4000" style="font-size:.9rem;"></textarea>
                        <div class="form-text text-end"><span id="dmCharCount">0</span>/4000</div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;padding:.75rem 1.5rem;">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:#16a34a;color:#fff;border:none;padding:.4rem 1.2rem;">
                        <i class="bi bi-send me-1"></i>Kirim
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Preview Modal -->
<div class="modal fade" id="imgPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:transparent;border:none;box-shadow:none;">
            <div class="modal-body text-center p-0 position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute" style="top:.5rem;right:.5rem;z-index:10;" data-bs-dismiss="modal" aria-label="Close"></button>
                <img id="imgPreviewSrc" src="" class="img-fluid rounded" style="max-height:80vh;">
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function(e) {
    // Image preview
    const thumb = e.target.closest('.msg-thumb');
    if (thumb) {
        e.preventDefault();
        document.getElementById('imgPreviewSrc').src = thumb.dataset.full;
        new bootstrap.Modal(document.getElementById('imgPreviewModal')).show();
    }
    // Send DM button
    const dmBtn = e.target.closest('.btn-send-dm');
    if (dmBtn) {
        document.getElementById('dmPhone').value = dmBtn.dataset.phone;
        document.getElementById('dmName').textContent = dmBtn.dataset.name;
        document.getElementById('dmMessage').value = '';
        document.getElementById('dmCharCount').textContent = '0';
        new bootstrap.Modal(document.getElementById('sendDmModal')).show();
    }
});
// Char counter
const dmMsg = document.getElementById('dmMessage');
if (dmMsg) {
    dmMsg.addEventListener('input', function() {
        document.getElementById('dmCharCount').textContent = this.value.length;
    });
}
</script>
@endpush
