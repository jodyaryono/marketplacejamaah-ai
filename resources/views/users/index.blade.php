@extends('layouts.app')
@section('title', 'Pengguna')
@section('breadcrumb', 'Pengguna')

@section('content')
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-person-gear me-2" style="color:#818cf8;"></i>Manajemen Pengguna</h1>
        <p class="mb-0 mt-1" style="color:#6b7280;font-size:.875rem;">Kelola akun dan hak akses pengguna</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="bi bi-person-plus me-2"></i>Tambah Pengguna</button>
</div>

<div class="page-body">
    <!-- Analytics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card card-blue">
                <div class="stat-icon mb-2"><i class="bi bi-people"></i></div>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <div class="stat-label">Total Pengguna</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-emerald">
                <div class="stat-icon mb-2"><i class="bi bi-person-check"></i></div>
                <div class="stat-value">{{ $stats['active'] }}</div>
                <div class="stat-label">Aktif</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-amber">
                <div class="stat-icon mb-2"><i class="bi bi-shield-check"></i></div>
                <div class="stat-value">{{ $stats['admins'] }}</div>
                <div class="stat-label">Admin</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card card-rose">
                <div class="stat-icon mb-2"><i class="bi bi-headset"></i></div>
                <div class="stat-value">{{ $stats['operators'] }}</div>
                <div class="stat-label">Operator</div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th class="px-4">Pengguna</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Bergabung</th>
                            <th class="px-4"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td class="px-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:36px;height:36px;border-radius:50%;background:#eef2ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-person" style="color:#4f46e5;"></i>
                                    </div>
                                    <div>
                                        <div style="font-weight:600;color:#111827;font-size:.875rem;">{{ $user->name }}</div>
                                        <div style="font-size:.75rem;color:#6b7280;">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @foreach($user->roles as $role)
                                    @php $rc=['admin'=>['bg'=>'#fef9c3','c'=>'#92400e'],'operator'=>['bg'=>'#e0f2fe','c'=>'#0369a1'],'viewer'=>['bg'=>'#f3f4f6','c'=>'#6b7280']]; $r=$rc[$role->name]??$rc['viewer']; @endphp
                                    <span class="badge" style="background:{{ $r['bg'] }};color:{{ $r['c'] }};">{{ ucfirst($role->name) }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if($user->is_active)
                                    <span class="badge" style="background:#dcfce7;color:#15803d;">Aktif</span>
                                @else
                                    <span class="badge" style="background:#fee2e2;color:#b91c1c;">Nonaktif</span>
                                @endif
                            </td>
                            <td style="font-size:.78rem;color:#6b7280;">{{ $user->created_at->format('d M Y') }}</td>
                            <td class="px-4">
                                <div class="d-flex gap-1 align-items-center">
                                    <button class="btn btn-sm" style="background:#eef2ff;border:none;color:#4f46e5;padding:.2rem .5rem;"
                                        onclick="editUser({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->email }}', '{{ $user->roles->first()?->name }}', {{ $user->is_active ? 'true' : 'false' }}, '{{ addslashes($user->phone ?? '') }}')"
                                        data-bs-toggle="modal" data-bs-target="#editUserModal">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @if($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm" style="background:#fee2e2;border:none;color:#b91c1c;padding:.2rem .5rem;"
                                            onclick="return confirm('Hapus pengguna {{ addslashes($user->name) }}?')"><i class="bi bi-trash"></i></button>
                                    </form>
                                    @else
                                    <span style="font-size:.75rem;color:#6b7280;">(Anda)</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5" style="color:#9ca3af;">Belum ada pengguna</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                    <h5 class="modal-title">Tambah Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nama</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Password</label>
                        <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="viewer">Viewer</option>
                            <option value="operator">Operator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nomor HP WhatsApp <span style="color:#6b7280;">(format: 628xxx)</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="628xxxxxxxxxxxx">
                        <div style="font-size:.72rem;color:#6b7280;margin-top:.25rem;">Digunakan untuk notifikasi pelanggaran dari bot AI</div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                @csrf @method('PUT')
                <div class="modal-header" style="border-bottom:1px solid #e5e7eb;">
                    <h5 class="modal-title">Edit Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nama</label>
                        <input type="text" name="name" id="editUserName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Email</label>
                        <input type="email" name="email" id="editUserEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Password Baru <span style="color:#6b7280;">(kosongkan jika tidak diubah)</span></label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Role</label>
                        <select name="role" id="editUserRole" class="form-select">
                            <option value="viewer">Viewer</option>
                            <option value="operator">Operator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="color:#374151;font-size:.82rem;">Nomor HP WhatsApp <span style="color:#6b7280;">(format: 628xxx)</span></label>
                        <input type="text" name="phone" id="editUserPhone" class="form-control" placeholder="628xxxxxxxxxxxx">
                        <div style="font-size:.72rem;color:#6b7280;margin-top:.25rem;">Digunakan untuk notifikasi pelanggaran dari bot AI</div>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editUserActive" value="1">
                        <label class="form-check-label" for="editUserActive" style="color:#374151;font-size:.875rem;">Aktif</label>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #e5e7eb;">
                    <button type="button" class="btn btn-sm" style="background:#f3f4f6;border:1px solid #d1d5db;color:#374151;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function editUser(id, name, email, role, active, phone) {
    document.getElementById('editUserForm').action = '/users/'+id;
    document.getElementById('editUserName').value = name;
    document.getElementById('editUserEmail').value = email;
    document.getElementById('editUserRole').value = role || 'viewer';
    document.getElementById('editUserActive').checked = active;
    document.getElementById('editUserPhone').value = phone || '';
}
</script>
@endsection
