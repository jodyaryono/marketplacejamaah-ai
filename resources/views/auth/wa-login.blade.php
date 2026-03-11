@extends('layouts.public')
@section('title', 'Masuk via WhatsApp — Marketplace Jamaah')

@section('styles')
<style>
    .wa-login-wrap { min-height:calc(100vh - 140px); display:flex; align-items:center; justify-content:center; padding:2rem 1rem; }
    .wa-login-card { background:#fff; border:1.5px solid #a7f3d0; border-radius:24px; padding:2.5rem; max-width:420px; width:100%; box-shadow:0 12px 40px rgba(5,150,105,.14); }
    .wa-icon-wrap { width:72px; height:72px; border-radius:20px; background:linear-gradient(135deg,#059669,#34d399); display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; box-shadow:0 8px 24px rgba(5,150,105,.4); }
    .wa-icon-wrap i { color:#fff; font-size:2rem; }
    .otp-input { letter-spacing:.5rem; font-size:1.5rem; font-weight:800; text-align:center; }
    .btn-wa-green { background:linear-gradient(135deg,#059669,#10b981); color:#fff; border:none; border-radius:12px; padding:.75rem; font-size:1rem; font-weight:700; transition:all .2s; }
    .btn-wa-green:hover { color:#fff; transform:translateY(-1px); box-shadow:0 6px 20px rgba(5,150,105,.4); }
    .step-indicator { display:flex; gap:.5rem; justify-content:center; margin-bottom:1.5rem; }
    .step-dot { width:10px; height:10px; border-radius:50%; background:#d1fae5; transition:all .3s; }
    .step-dot.active { background:#059669; transform:scale(1.2); }
</style>
@endsection

@section('content')
<div class="wa-login-wrap">
    <div class="wa-login-card">
        <div class="wa-icon-wrap"><i class="bi bi-whatsapp"></i></div>
        <h2 style="font-size:1.4rem;font-weight:800;text-align:center;color:#111827;margin-bottom:.5rem;">Masuk via WhatsApp</h2>
        <p style="text-align:center;color:#6b7280;font-size:.88rem;margin-bottom:1.5rem;">
            Masukkan nomor WA kamu — kami kirim kode OTP.<br>Tidak perlu password! 🎉
        </p>

        <div class="step-indicator">
            <div class="step-dot {{ !session('otp_sent') ? 'active' : '' }}"></div>
            <div class="step-dot {{ session('otp_sent') ? 'active' : '' }}"></div>
        </div>

        @if($errors->any())
            <div class="alert alert-danger rounded-3" style="font-size:.85rem;">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('success'))
            <div class="alert alert-success rounded-3" style="font-size:.85rem;">{{ session('success') }}</div>
        @endif

        @if(!session('otp_sent'))
        {{-- Step 1: Enter phone number --}}
        <form method="POST" action="{{ route('wa.otp.request') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-600" style="font-weight:600;font-size:.88rem;">Nomor WhatsApp</label>
                <div class="input-group">
                    <span class="input-group-text" style="background:#ecfdf5;border-color:#a7f3d0;color:#059669;font-weight:700;">🇮🇩 +62</span>
                    <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror"
                           placeholder="812xxxx atau 08xxxx"
                           value="{{ old('phone') }}"
                           style="border-color:#a7f3d0;font-size:.95rem;"
                           autofocus>
                </div>
                <div style="font-size:.75rem;color:#6b7280;margin-top:.35rem;">Contoh: 08123456789 atau 628123456789</div>
            </div>
            <button type="submit" class="btn btn-wa-green w-100">
                <i class="bi bi-send me-2"></i>Kirim Kode OTP via WA
            </button>
        </form>

        @else
        {{-- Step 2: Enter OTP --}}
        <div class="alert" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;font-size:.85rem;color:#065f46;">
            <i class="bi bi-check-circle-fill me-2"></i>
            Kode OTP sudah dikirim ke WhatsApp <strong>{{ session('otp_phone') }}</strong>. Cek pesan masuk.
        </div>
        <form method="POST" action="{{ route('wa.otp.verify') }}">
            @csrf
            <input type="hidden" name="phone" value="{{ session('otp_phone') }}">
            <div class="mb-3">
                <label class="form-label fw-600" style="font-weight:600;font-size:.88rem;">Masukkan Kode OTP (6 digit)</label>
                <input type="text" name="otp" class="form-control otp-input @error('otp') is-invalid @enderror"
                       placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                       inputmode="numeric" autofocus
                       style="border-color:#a7f3d0;border-radius:12px;">
                <div style="font-size:.75rem;color:#6b7280;margin-top:.35rem;">Kode berlaku 10 menit.</div>
            </div>
            <button type="submit" class="btn btn-wa-green w-100">
                <i class="bi bi-shield-check me-2"></i>Verifikasi & Masuk
            </button>
        </form>
        <div class="text-center mt-3">
            <a href="{{ route('wa.login') }}" style="font-size:.82rem;color:#6b7280;text-decoration:none;">
                <i class="bi bi-arrow-left me-1"></i>Ganti nomor / Kirim ulang kode
            </a>
        </div>
        @endif

        <div class="mt-4 pt-3" style="border-top:1px solid #f0fdf4;text-align:center;">
            <div style="font-size:.75rem;color:#9ca3af;">
                <i class="bi bi-shield-lock me-1"></i>
                Login aman via WhatsApp · Token berlaku 6 bulan
            </div>
        </div>
    </div>
</div>
@endsection
