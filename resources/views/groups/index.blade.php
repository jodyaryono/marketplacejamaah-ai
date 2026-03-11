@extends('layouts.app')
@section('title', 'Grup WhatsApp')
@section('breadcrumb', 'Grup WhatsApp')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-people me-2" style="color:#059669;"></i>Grup WhatsApp</h1>
        <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Manajemen grup yang terpantau</p>
    </div>
    @can('admin')
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
        <i class="bi bi-plus-circle me-2"></i>Tambah Grup
    </button>
    @else
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGroupModal">
        <i class="bi bi-plus-circle me-2"></i>Tambah Grup
    </button>
    @endcan
</div>

<div class="page-body">

    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-people"></i></div>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <div class="stat-label">Total Grup</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-toggle-on"></i></div>
                <div class="stat-value">{{ $stats['active'] }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-chat-dots"></i></div>
                <div class="stat-value">{{ number_format($stats['total_messages']) }}</div>
                <div class="stat-label">Total Pesan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-megaphone"></i></div>
                <div class="stat-value">{{ number_format($stats['total_ads']) }}</div>
                <div class="stat-label">Total Iklan</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between py-3 px-4">
            <span style="font-weight:700;color:#111827;font-size:.95rem;">
                <i class="bi bi-list-ul me-2" style="color:#059669;"></i>Daftar Grup ({{ $groups->total() }})
            </span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addGroupModal">
                <i class="bi bi-plus-circle me-1"></i> Tambah Grup
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="px-4" style="width:25%;">Nama Grup</th>
                            <th class="text-center">Anggota</th>
                            <th class="text-center">Admin</th>
                            <th class="text-center">Pesan</th>
                            <th class="text-center">Iklan</th>
                            <th>Pesan Terakhir</th>
                            <th class="text-center">Status</th>
                            <th class="px-4 text-center" style="width:180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $group)
                        <tr>
                            <td class="px-4">
                                <div style="font-weight:600;color:#111827;">{{ $group->group_name }}</div>
                                @if($group->description)
                                    <div style="font-size:.75rem;color:#9ca3af;">{{ Str::limit($group->description, 40) }}</div>
                                @endif
                                <code style="font-size:.67rem;color:#9ca3af;">{{ Str::limit($group->group_id, 22) }}</code>
                            </td>
                            {{-- Member Count --}}
                            <td class="text-center">
                                <button type="button"
                                    class="btn btn-link p-0 text-decoration-none"
                                    onclick="showParticipants({{ $group->id }}, 'members', '{{ addslashes($group->group_name) }}')"
                                    data-bs-toggle="tooltip" title="Klik untuk lihat anggota">
                                    <span style="display:inline-flex;align-items:center;gap:.25rem;background:#dbeafe;color:#1d4ed8;border-radius:20px;padding:.25rem .65rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                                        <i class="bi bi-people-fill"></i>
                                        {{ $group->unique_senders ?? 0 }}
                                    </span>
                                </button>
                            </td>
                            {{-- Admin Count --}}
                            <td class="text-center">
                                <button type="button"
                                    class="btn btn-link p-0 text-decoration-none"
                                    onclick="showParticipants({{ $group->id }}, 'admins', '{{ addslashes($group->group_name) }}')"
                                    data-bs-toggle="tooltip" title="Klik untuk lihat admin">
                                    <span style="display:inline-flex;align-items:center;gap:.25rem;background:#fef3c7;color:#92400e;border-radius:20px;padding:.25rem .65rem;font-size:.78rem;font-weight:700;cursor:pointer;">
                                        <i class="bi bi-shield-fill"></i>
                                        {{ $group->admin_count }}
                                    </span>
                                </button>
                            </td>
                            <td class="text-center"><span style="color:#059669;font-weight:700;">{{ number_format($group->messages_count) }}</span></td>
                            <td class="text-center"><span style="color:#d97706;font-weight:700;">{{ number_format($group->ad_count) }}</span></td>
                            <td style="font-size:.78rem;color:#6b7280;">{{ $group->last_message_at ? $group->last_message_at->diffForHumans() : '-' }}</td>
                            <td class="text-center">
                                @if($group->is_active)
                                    <span class="badge" style="background:#dcfce7;color:#15803d;padding:.35rem .7rem;border-radius:20px;font-size:.72rem;font-weight:600;">
                                        <i class="bi bi-circle-fill me-1" style="font-size:.45rem;vertical-align:middle;"></i>Aktif
                                    </span>
                                @else
                                    <span class="badge" style="background:#fee2e2;color:#b91c1c;padding:.35rem .7rem;border-radius:20px;font-size:.72rem;font-weight:600;">
                                        <i class="bi bi-circle me-1" style="font-size:.45rem;vertical-align:middle;"></i>Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    {{-- Sync --}}
                                    <form method="POST" action="{{ route('groups.sync', $group) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm"
                                            style="background:linear-gradient(135deg,#0891b2,#0ea5e9);color:#fff;border:none;border-radius:8px;padding:.28rem .6rem;font-size:.73rem;font-weight:600;"
                                            data-bs-toggle="tooltip" title="Sync anggota dari WhatsApp">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                    </form>
                                    {{-- Only admin can send toggle --}}
                                    <form method="POST" action="{{ route('groups.announce', $group) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm"
                                            style="background:{{ $group->only_admins_can_send ? 'linear-gradient(135deg,#dc2626,#ef4444)' : 'linear-gradient(135deg,#475569,#64748b)' }};color:#fff;border:none;border-radius:8px;padding:.28rem .6rem;font-size:.73rem;font-weight:600;"
                                            data-bs-toggle="tooltip"
                                            title="{{ $group->only_admins_can_send ? 'Hanya admin bisa kirim — klik untuk izinkan semua' : 'Semua bisa kirim — klik untuk batasi ke admin saja' }}">
                                            <i class="bi {{ $group->only_admins_can_send ? 'bi-lock-fill' : 'bi-unlock-fill' }}"></i>
                                        </button>
                                    </form>
                                    {{-- Send Message --}}
                                    <button class="btn btn-sm"
                                        style="background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff;border:none;border-radius:8px;padding:.28rem .6rem;font-size:.73rem;font-weight:600;"
                                        onclick="openSendMessage({{ $group->id }}, '{{ addslashes($group->group_name) }}', '{{ addslashes($group->group_id) }}');"
                                        data-bs-toggle="tooltip" title="Kirim pesan ke grup">
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                    {{-- Edit --}}
                                    <button class="btn btn-sm"
                                        style="background:linear-gradient(135deg,#059669,#10b981);color:#fff;border:none;border-radius:8px;padding:.28rem .6rem;font-size:.73rem;font-weight:600;"
                                        onclick="editGroup({{ $group->id }}, '{{ addslashes($group->group_name) }}', '{{ addslashes($group->group_id) }}', '{{ addslashes($group->description ?? '') }}', {{ $group->is_active ? 'true' : 'false' }})"
                                        data-bs-toggle="modal" data-bs-target="#editGroupModal">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    {{-- Hapus --}}
                                    <form method="POST" action="{{ route('groups.destroy', $group) }}" class="d-inline"
                                        onsubmit="return confirm('Hapus grup {{ addslashes($group->group_name) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm"
                                            style="background:linear-gradient(135deg,#db2777,#e11d48);color:#fff;border:none;border-radius:8px;padding:.28rem .6rem;font-size:.73rem;font-weight:600;">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5" style="color:#9ca3af;">
                                <i class="bi bi-people" style="font-size:2.5rem;display:block;margin-bottom:.75rem;color:#a7f3d0;"></i>
                                <div style="font-weight:600;color:#374151;margin-bottom:.25rem;">Belum ada grup terdaftar</div>
                                <div style="font-size:.82rem;">Klik <strong>Tambah Grup</strong> untuk menambahkan grup WhatsApp</div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($groups->hasPages())
        <div class="card-footer px-4 py-3" style="background:#f9fafb;border-top:1px solid #e5e7eb;">
            {{ $groups->links() }}
        </div>
        @endif
    </div>
</div>

<!-- Send Message Modal -->
<div class="modal fade" id="sendMessageModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <form method="POST" id="sendMessageForm">
                @csrf
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;background:#f5f3ff;border-radius:16px 16px 0 0;">
                    <h5 class="modal-title" style="font-weight:700;color:#111827;">
                        <i class="bi bi-send-fill me-2" style="color:#7c3aed;"></i>Kirim Pesan ke Grup
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;font-weight:600;">Grup Tujuan</label>
                        <div id="sendMsgGroupName" style="font-size:.875rem;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:.55rem .85rem;color:#374151;font-weight:600;"></div>
                        <div id="sendMsgGroupId" style="font-size:.72rem;color:#9ca3af;font-family:monospace;margin-top:.3rem;"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" style="color:#374151;font-size:.82rem;font-weight:600;">Pesan <span style="color:#e11d48;">*</span></label>
                        <textarea name="message" id="sendMsgText" class="form-control" rows="5" required placeholder="Ketik pesan yang akan dikirim ke grup..."></textarea>
                        <div style="font-size:.75rem;color:#6b7280;margin-top:.3rem;"><i class="bi bi-info-circle me-1"></i>Pesan akan dikirim dari akun WhatsApp yang terhubung sebagai bot.</div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;border-radius:8px;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm" style="background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff;border:none;border-radius:8px;font-weight:600;">
                        <i class="bi bi-send me-1"></i>Kirim Pesan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <form method="POST" action="{{ route('groups.store') }}">
                @csrf
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;background:#f0fdf4;border-radius:16px 16px 0 0;">
                    <h5 class="modal-title" style="font-weight:700;color:#111827;">
                        <i class="bi bi-plus-circle-fill me-2" style="color:#059669;"></i>Tambah Grup WhatsApp
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;font-weight:600;">Nama Grup <span style="color:#e11d48;">*</span></label>
                        <input type="text" name="group_name" class="form-control" required placeholder="contoh: Marketplace Jamaah Jakarta">
                        <div style="font-size:.75rem;color:#6b7280;margin-top:.3rem;"><i class="bi bi-info-circle me-1"></i>Group ID akan dibuat otomatis. Cocokkan nama grup persis dengan nama grup di WhatsApp agar terhubung saat pesan masuk.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" style="color:#374151;font-size:.82rem;font-weight:600;">Deskripsi</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Deskripsi singkat grup (opsional)"></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;border-radius:8px;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Group Modal -->
<div class="modal fade" id="editGroupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <form method="POST" id="editGroupForm">
                @csrf @method('PUT')
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;background:#f0fdf4;border-radius:16px 16px 0 0;">
                    <h5 class="modal-title" style="font-weight:700;color:#111827;">
                        <i class="bi bi-pencil-fill me-2" style="color:#059669;"></i>Edit Grup WhatsApp
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;font-weight:600;">Nama Grup <span style="color:#e11d48;">*</span></label>
                        <input type="text" name="group_name" id="editGroupName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;font-weight:600;">Group ID WhatsApp</label>
                        <div id="editGroupIdDisplay" style="font-size:.8rem;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:8px;padding:.55rem .85rem;color:#374151;font-family:monospace;word-break:break-all;"></div>
                        <input type="hidden" name="group_id" id="editGroupId">
                        <div style="font-size:.75rem;color:#9ca3af;margin-top:.3rem;"><i class="bi bi-info-circle me-1"></i>Group ID otomatis, tidak perlu diubah</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;font-weight:600;">Deskripsi</label>
                        <textarea name="description" id="editGroupDesc" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-0">
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editGroupActive" value="1">
                            <label class="form-check-label" for="editGroupActive" style="color:#374151;font-size:.875rem;font-weight:600;">Grup Aktif</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;border-radius:8px;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i>Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Participants Modal -->
<div class="modal fade" id="participantsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header" id="participantsModalHeader" style="border-radius:16px 16px 0 0;">
                <h5 class="modal-title" id="participantsModalTitle" style="font-weight:700;color:#111827;">
                    <i class="bi bi-people-fill me-2"></i>Daftar Anggota
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body px-0 py-0">
                <div id="participantsLoading" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <div class="mt-2 text-muted small">Memuat data...</div>
                </div>
                <div id="participantsList" style="display:none;">
                    <div class="px-4 py-2" style="background:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:.8rem;color:#6b7280;" id="participantsSummary"></div>
                    <div id="participantsItems"></div>
                </div>
                <div id="participantsEmpty" style="display:none;" class="text-center py-5">
                    <i class="bi bi-people" style="font-size:2.5rem;color:#a7f3d0;display:block;margin-bottom:.75rem;"></i>
                    <div style="font-weight:600;color:#374151;">Belum ada data anggota</div>
                    <div class="text-muted small mt-1">Tekan tombol <strong>Sync</strong> untuk mengambil dari WhatsApp</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const participantsRoute = '{{ url("/groups") }}';

async function showParticipants(groupId, type, groupName) {
    const modal = new bootstrap.Modal(document.getElementById('participantsModal'));
    const header = document.getElementById('participantsModalHeader');
    const title  = document.getElementById('participantsModalTitle');
    const loading   = document.getElementById('participantsLoading');
    const list      = document.getElementById('participantsList');
    const empty     = document.getElementById('participantsEmpty');
    const summary   = document.getElementById('participantsSummary');
    const items     = document.getElementById('participantsItems');

    // Reset
    loading.style.display = '';
    list.style.display = 'none';
    empty.style.display = 'none';

    if (type === 'admins') {
        header.style.background = '#fffbeb';
        title.innerHTML = '<i class="bi bi-shield-fill me-2" style="color:#d97706;"></i>Admin — ' + groupName;
    } else {
        header.style.background = '#eff6ff';
        title.innerHTML = '<i class="bi bi-people-fill me-2" style="color:#2563eb;"></i>Anggota — ' + groupName;
    }

    modal.show();

    try {
        const res  = await fetch(`${participantsRoute}/${groupId}/participants`);
        const data = await res.json();

        const arr = type === 'admins' ? data.admins : data.members;

        loading.style.display = 'none';

        if (!arr || arr.length === 0) {
            empty.style.display = '';
            return;
        }

        const roleColors = { seller:'#dcfce7', buyer:'#dbeafe', both:'#fef9c3' };
        const roleText   = { seller:'Penjual', buyer:'Pembeli', both:'Penjual & Pembeli', null:'-' };

        summary.textContent = arr.length + ' ' + (type === 'admins' ? 'admin' : 'anggota') + ' ditemukan';
        items.innerHTML = arr.map(p => {
            const phone     = p.phone ?? p.phone_number ?? '-';
            const name      = p.name ?? 'N/A';
            const isReg     = p.is_registered;
            const role      = p.member_role ?? p.role ?? null;
            const sell      = p.sell_products ?? null;
            const buy       = p.buy_products ?? null;
            const roleLabel = roleText[role] ?? '-';
            const roleBg    = roleColors[role] ?? '#f3f4f6';
            return `<div class="d-flex align-items-start gap-3 px-4 py-3" style="border-bottom:1px solid #f3f4f6;">
                <div style="width:36px;height:36px;border-radius:50%;background:${isReg ? '#dcfce7' : '#fee2e2'};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi ${isReg ? 'bi-person-check-fill' : 'bi-person-x-fill'}" style="color:${isReg ? '#059669' : '#dc2626'};font-size:1rem;"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div style="font-weight:600;color:#111827;font-size:.87rem;">${name}</div>
                    <div style="font-size:.78rem;color:#6b7280;font-family:monospace;">📞 ${phone}</div>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <span style="font-size:.7rem;padding:.2rem .5rem;border-radius:12px;background:${roleBg};font-weight:600;">${roleLabel}</span>
                        ${isReg ? '<span style="font-size:.7rem;padding:.2rem .5rem;border-radius:12px;background:#dcfce7;color:#065f46;font-weight:600;">✓ Terdaftar</span>' : '<span style="font-size:.7rem;padding:.2rem .5rem;border-radius:12px;background:#fee2e2;color:#991b1b;font-weight:600;">Belum Daftar</span>'}
                    </div>
                    ${sell ? `<div style="font-size:.72rem;color:#059669;margin-top:.25rem;">🏪 Jual: ${sell}</div>` : ''}
                    ${buy  ? `<div style="font-size:.72rem;color:#2563eb;margin-top:.15rem;">🛍️ Cari: ${buy}</div>`  : ''}
                </div>
            </div>`;
        }).join('');

        list.style.display = '';
    } catch (e) {
        loading.style.display = 'none';
        empty.style.display = '';
    }
}

function openSendMessage(id, name, gid) {
    document.getElementById('sendMessageForm').action = '/groups/' + id + '/send-message';
    document.getElementById('sendMsgGroupName').textContent = name;
    document.getElementById('sendMsgGroupId').textContent = gid;
    document.getElementById('sendMsgText').value = '';
    const modal = new bootstrap.Modal(document.getElementById('sendMessageModal'));
    modal.show();
}

function editGroup(id, name, gid, desc, active) {
    document.getElementById('editGroupForm').action = '/groups/'+id;
    document.getElementById('editGroupName').value = name;
    document.getElementById('editGroupId').value = gid;
    document.getElementById('editGroupIdDisplay').textContent = gid;
    document.getElementById('editGroupDesc').value = desc;
    document.getElementById('editGroupActive').checked = active;
}

// Init tooltips
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});
</script>
@endsection
