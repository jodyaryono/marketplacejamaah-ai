@extends('layouts.app')
@section('title', 'Kontak')
@section('breadcrumb', 'Kontak')

@section('content')
<div class="page-header">
    <h1><i class="bi bi-person-lines-fill me-2" style="color:#f472b6;"></i>Kontak</h1>
    <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Semua pengirim pesan yang terdeteksi</p>
</div>

<div class="page-body">
    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-people"></i></div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total Kontak</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value">{{ number_format($stats['new_today']) }}</div>
                <div class="stat-label">Baru Hari Ini</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-megaphone"></i></div>
                <div class="stat-value">{{ number_format($stats['sellers']) }}</div>
                <div class="stat-label">Pernah Beriklan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-activity"></i></div>
                <div class="stat-value">{{ number_format($stats['active_week']) }}</div>
                <div class="stat-label">Aktif Minggu Ini</div>
            </div>
        </div>
    </div>

    <!-- Search + Table -->
    <div class="card">
        <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" style="max-width:280px;" placeholder="Cari nama atau nomor WA..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                @if(request('search'))
                <a href="{{ route('contacts.index') }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Reset</a>
                @endif
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="px-4">Nama / Nomor</th>
                            <th>Pesan</th>
                            <th>Iklan</th>
                            <th>Terakhir Aktif</th>
                            <th class="px-4 text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contacts as $contact)
                        <tr>
                            <td class="px-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:34px;height:34px;border-radius:50%;background:{{ ['#eef2ff','#fce7f3','#dcfce7','#fef9c3'][$loop->index % 4] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-person-fill" style="font-size:.85rem;color:#6b7280;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;color:#111827;font-size:.875rem;">{{ $contact->name ?: '-' }}</div>
                                        <div style="font-size:.75rem;color:#6b7280;">{{ $contact->phone_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span style="color:#4f46e5;">{{ number_format($contact->message_count) }}</span></td>
                            <td>
                                @if($contact->ad_count > 0)
                                    <span style="color:#92400e;">{{ number_format($contact->ad_count) }}</span>
                                @else
                                    <span style="color:#9ca3af;">0</span>
                                @endif
                            </td>
                            <td style="font-size:.78rem;color:#6b7280;">{{ $contact->last_seen ? $contact->last_seen->diffForHumans() : '-' }}</td>
                            <td class="px-4 text-end">
                                <div class="d-flex gap-1 justify-content-end">
                                    <!-- Lihat -->
                                    <a href="{{ route('contacts.show', $contact) }}" class="btn btn-sm" title="Lihat Detail" style="background:#eef2ff;border:none;color:#4f46e5;padding:.25rem .5rem;"><i class="bi bi-eye"></i></a>
                                    <!-- Kirim Pesan -->
                                    <button type="button" class="btn btn-sm" title="Kirim Pesan WA" style="background:#dcfce7;border:none;color:#16a34a;padding:.25rem .5rem;"
                                        onclick="openSendModal({{ $contact->id }}, '{{ addslashes($contact->name ?: $contact->phone_number) }}', '{{ $contact->phone_number }}')">
                                        <i class="bi bi-chat-dots"></i>
                                    </button>
                                    <!-- Edit -->
                                    <button type="button" class="btn btn-sm" title="Edit Kontak" style="background:#fef9c3;border:none;color:#b45309;padding:.25rem .5rem;"
                                        onclick="openEditModal({{ $contact->id }}, '{{ addslashes($contact->name) }}', '{{ $contact->phone_number }}', '{{ $contact->member_role }}', {{ $contact->is_blocked ? 'true' : 'false' }})">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <!-- Hapus -->
                                    <form method="POST" action="{{ route('contacts.destroy', $contact) }}" onsubmit="return confirm('Hapus kontak {{ addslashes($contact->name ?: $contact->phone_number) }}?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm" title="Hapus" style="background:#fee2e2;border:none;color:#dc2626;padding:.25rem .5rem;"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5" style="color:#9ca3af;">
                                <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                                Belum ada kontak
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($contacts->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:.75rem 1rem;">
            <span style="font-size:.8rem;color:#6b7280;">{{ $contacts->firstItem() }}–{{ $contacts->lastItem() }} dari {{ $contacts->total() }}</span>
            {{ $contacts->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>

<!-- Modal: Kirim Pesan WA -->
<div class="modal fade" id="sendModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;border:none;">
            <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                <h5 class="modal-title fw-semibold"><i class="bi bi-chat-dots me-2" style="color:#16a34a;"></i>Kirim Pesan WA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="sendForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="mb-3" style="font-size:.875rem;color:#6b7280;">Kirim ke: <strong id="sendName"></strong> (<span id="sendPhone"></span>)</p>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Pesan</label>
                        <textarea name="message" class="form-control" rows="4" placeholder="Ketik pesan..." maxlength="1000" required></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send me-1"></i>Kirim</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Kontak -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:12px;border:none;">
            <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                <h5 class="modal-title fw-semibold"><i class="bi bi-pencil me-2" style="color:#b45309;"></i>Edit Kontak</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-medium">Nama</label>
                        <input type="text" name="name" id="editName" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Nomor WA</label>
                        <input type="text" name="phone_number" id="editPhone" class="form-control" required maxlength="50">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-medium">Role</label>
                        <select name="member_role" id="editRole" class="form-select">
                            <option value="">— Belum ditentukan —</option>
                            <option value="buyer">Pembeli</option>
                            <option value="seller">Penjual</option>
                            <option value="both">Keduanya</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="hidden" name="is_blocked" value="0">
                        <input type="checkbox" name="is_blocked" id="editBlocked" class="form-check-input" value="1">
                        <label for="editBlocked" class="form-check-label" style="font-size:.875rem;">Blokir kontak ini</label>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" data-bs-dismiss="modal" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openSendModal(id, name, phone) {
    document.getElementById('sendName').textContent = name;
    document.getElementById('sendPhone').textContent = phone;
    document.getElementById('sendForm').action = '/contacts/' + id + '/send-message';
    document.getElementById('sendForm').querySelector('textarea').value = '';
    new bootstrap.Modal(document.getElementById('sendModal')).show();
}

function openEditModal(id, name, phone, role, blocked) {
    document.getElementById('editName').value = name;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editRole').value = role || '';
    document.getElementById('editBlocked').checked = blocked;
    document.getElementById('editForm').action = '/contacts/' + id;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
@endsection


@section('content')
<div class="page-header">
    <h1><i class="bi bi-person-lines-fill me-2" style="color:#f472b6;"></i>Kontak</h1>
    <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Semua pengirim pesan yang terdeteksi</p>
</div>

<div class="page-body">
    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-people"></i></div>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <div class="stat-label">Total Kontak</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-value">{{ number_format($stats['new_today']) }}</div>
                <div class="stat-label">Baru Hari Ini</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-megaphone"></i></div>
                <div class="stat-value">{{ number_format($stats['sellers']) }}</div>
                <div class="stat-label">Pernah Beriklan</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-activity"></i></div>
                <div class="stat-value">{{ number_format($stats['active_week']) }}</div>
                <div class="stat-label">Aktif Minggu Ini</div>
            </div>
        </div>
    </div>

    <!-- Search + Table -->
    <div class="card">
        <div class="card-header" style="background:transparent;border-bottom:1px solid #e5e7eb;padding:.75rem 1.25rem;">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" style="max-width:280px;" placeholder="Cari nama atau nomor WA..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-primary btn-sm">Cari</button>
                @if(request('search'))
                <a href="{{ route('contacts.index') }}" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;">Reset</a>
                @endif
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="px-4">Nama / Nomor</th>
                            <th>Pesan</th>
                            <th>Iklan</th>
                            <th>Terakhir Aktif</th>
                            <th class="px-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contacts as $contact)
                        <tr>
                            <td class="px-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:34px;height:34px;border-radius:50%;background:{{ ['#eef2ff','#fce7f3','#dcfce7','#fef9c3'][$loop->index % 4] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-person-fill" style="font-size:.85rem;color:#6b7280;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;color:#111827;font-size:.875rem;">{{ $contact->name ?: '-' }}</div>
                                        <div style="font-size:.75rem;color:#6b7280;">{{ $contact->phone_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span style="color:#4f46e5;">{{ number_format($contact->message_count) }}</span></td>
                            <td>
                                @if($contact->ad_count > 0)
                                    <span style="color:#92400e;">{{ number_format($contact->ad_count) }}</span>
                                @else
                                    <span style="color:#9ca3af;">0</span>
                                @endif
                            </td>
                            <td style="font-size:.78rem;color:#6b7280;">{{ $contact->last_seen ? $contact->last_seen->diffForHumans() : '-' }}</td>
                            <td class="px-4">
                                <a href="{{ route('contacts.show', $contact) }}" class="btn btn-sm" style="background:#eef2ff;border:none;color:#4f46e5;padding:.2rem .6rem;"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5" style="color:#9ca3af;">
                                <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:.5rem;"></i>
                                Belum ada kontak
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($contacts->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center" style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:.75rem 1rem;">
            <span style="font-size:.8rem;color:#6b7280;">{{ $contacts->firstItem() }}–{{ $contacts->lastItem() }} dari {{ $contacts->total() }}</span>
            {{ $contacts->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
