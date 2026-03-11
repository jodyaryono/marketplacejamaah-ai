<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — MarketplaceJamaah AI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; color: #111827; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .login-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 420px; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
        .form-control { background: #ffffff !important; border-color: #d1d5db !important; color: #111827 !important; border-radius: 8px; padding: .75rem 1rem; }
        .form-control:focus { border-color: #6366f1 !important; box-shadow: 0 0 0 3px rgba(99,102,241,.15) !important; }
        .form-control::placeholder { color: #9ca3af; }
        .btn-primary { background: #6366f1; border-color: #6366f1; border-radius: 8px; padding: .75rem; font-weight: 600; }
        .btn-primary:hover { background: #4f46e5; border-color: #4f46e5; }
        .form-label { color: #374151; font-size: .875rem; font-weight: 500; }
        .logo-box { width: 52px; height: 52px; background: linear-gradient(135deg,#6366f1,#8b5cf6); border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="text-center mb-4">
        <div class="logo-box mx-auto mb-3">
            <i class="bi bi-robot text-white" style="font-size:1.5rem;"></i>
        </div>
        <h4 class="fw-bold mb-1" style="color:#111827;">MarketplaceJamaah AI</h4>
        <p class="text-muted" style="font-size:.875rem;">Masuk ke dashboard</p>
    </div>

    <form method="POST" action="{{ route('login.post') }}">
        @csrf
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                placeholder="admin@example.com" value="{{ old('email') }}" required autofocus>
            @error('email')
                <div class="invalid-feedback" style="color:#b91c1c;">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                placeholder="••••••••" required>
            @error('password')
                <div class="invalid-feedback" style="color:#b91c1c;">{{ $message }}</div>
            @enderror
        </div>
        <div class="mb-3 d-flex align-items-center gap-2">
            <input type="checkbox" name="remember" id="remember" class="form-check-input" style="background:#ffffff;border-color:#d1d5db;">
            <label for="remember" class="form-label mb-0" style="font-size:.8rem;">Ingat saya</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-box-arrow-in-right me-2"></i>Masuk
        </button>
    </form>

    <div class="text-center mt-4" style="font-size:.75rem;color:#9ca3af;">
        MarketplaceJamaah AI &copy; {{ date('Y') }}
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Shortcut: Ctrl+Enter → auto-fill admin & login
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        document.querySelector('input[name="email"]').value = 'admin@marketplacejamaah.id';
        document.querySelector('input[name="password"]').value = 'B15m1ll4h#2026';
        document.querySelector('form').submit();
    }
});
</script>
</body>
</html>
