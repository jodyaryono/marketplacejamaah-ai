<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\WaAuthController;
use App\Http\Controllers\AgentLogController;
use App\Http\Controllers\AiHealthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SystemMessageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public landing page
Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/marketing-tools', [LandingController::class, 'marketingTools'])->name('marketing-tools');
Route::get('/release-notes', [LandingController::class, 'releaseNotes'])->name('release-notes');

// ── Public product & seller pages (no auth required) ────────────────────────
Route::get('/produk/lagi', [LandingController::class, 'loadMore'])->name('listings.more');
Route::get('/p/{id}', [PublicController::class, 'listingDetail'])->name('public.listing')->where('id', '[0-9]+');
Route::get('/u/{phone}', [PublicController::class, 'sellerProfile'])->name('public.seller')->where('phone', '[0-9]+');

// ── WhatsApp OTP authentication (DISABLED — all member actions via bot now) ──
// Old login URL redirects to homepage
Route::get('/login-wa', fn () => redirect('/') )->name('wa.login');
Route::get('/saya', fn () => redirect('/') )->name('member.dashboard');
// Keep route names but redirect — prevents blade compile errors
Route::post('/login-wa/otp', fn () => redirect('/') )->name('wa.otp.request');
Route::post('/login-wa/verify', fn () => redirect('/') )->name('wa.otp.verify');
Route::post('/login-wa/logout', fn () => redirect('/') )->name('wa.logout');
Route::get('/saya/iklan/{id}/edit', fn () => redirect('/') )->name('member.listing.edit')->where('id', '[0-9]+');
Route::post('/saya/iklan/{id}/update', fn () => redirect('/') )->name('member.listing.update')->where('id', '[0-9]+');

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Protected routes
Route::middleware(['auth', 'check.active'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

    // Messages (Inbox)
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/send-dm', [MessageController::class, 'sendDm'])->name('messages.send-dm');
    Route::get('/messages/{message}', [MessageController::class, 'show'])->name('messages.show');

    // Listings (Marketplace)
    Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');
    Route::patch('/listings/{listing}/status', [ListingController::class, 'updateStatus'])->name('listings.status');
    Route::get('/listings/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('/listings/{listing}', [ListingController::class, 'update'])->name('listings.update');

    // Groups
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::get('/groups/{group}/participants', [GroupController::class, 'participants'])->name('groups.participants');
    Route::post('/groups/{group}/sync', [GroupController::class, 'sync'])->name('groups.sync');
    Route::post('/groups/{group}/announce', [GroupController::class, 'announce'])->name('groups.announce');
    Route::post('/groups/{group}/send-message', [GroupController::class, 'sendMessage'])->name('groups.send-message');

    // System Messages
    Route::get('/system-messages', [SystemMessageController::class, 'index'])->name('system-messages.index');
    Route::put('/system-messages/{systemMessage}', [SystemMessageController::class, 'update'])->name('system-messages.update');

    // Categories
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');

    // Contacts
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::post('/contacts/resend-onboarding-all', [ContactController::class, 'resendOnboardingAll'])->name('contacts.resend-onboarding-all');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy');
    Route::post('/contacts/{contact}/send-message', [ContactController::class, 'sendMessage'])->name('contacts.send-message');
    Route::post('/contacts/{contact}/resend-onboarding', [ContactController::class, 'resendOnboarding'])->name('contacts.resend-onboarding');
    Route::post('/contacts/{contact}/approve', [ContactController::class, 'approve'])->name('contacts.approve');
    Route::post('/contacts/{contact}/reject', [ContactController::class, 'reject'])->name('contacts.reject');

    // Agent Monitor
    Route::get('/agents', [AgentLogController::class, 'index'])->name('agents.index');
    Route::get('/agents/docs', [AgentLogController::class, 'docs'])->name('agents.docs');
    Route::post('/agents/prompts', [AgentLogController::class, 'updatePrompts'])->name('agents.update-prompts');

    // AI Health & Token Check
    Route::get('/ai-health', [AiHealthController::class, 'index'])->name('ai-health.index');
    Route::post('/ai-health/ping-gemini', [AiHealthController::class, 'pingGemini'])->name('ai-health.ping-gemini');
    Route::post('/ai-health/ping-whacenter', [AiHealthController::class, 'pingWhacenter'])->name('ai-health.ping-whacenter');
    Route::post('/ai-health/ping-database', [AiHealthController::class, 'pingDatabase'])->name('ai-health.ping-database');
    Route::post('/ai-health/ping-queue', [AiHealthController::class, 'pingQueue'])->name('ai-health.ping-queue');
    Route::post('/ai-health/ping-system', [AiHealthController::class, 'pingSystem'])->name('ai-health.ping-system');

    // Users & Roles (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/test-whacenter', [SettingsController::class, 'testWhacenter'])->name('settings.test-whacenter');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
});
