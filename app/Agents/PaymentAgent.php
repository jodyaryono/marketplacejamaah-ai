<?php

namespace App\Agents;

use App\Models\AgentLog;
use App\Models\Contact;
use App\Models\Listing;
use App\Models\Message;
use App\Models\UsycTransaction;
use App\Models\UsycWallet;
use App\Services\ArcPaymentService;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Log;

/**
 * PaymentAgent
 *
 * Agentic Economy core: listens to WhatsApp messages, detects purchase
 * intents, and autonomously executes USYC nanopayments on Arc blockchain.
 *
 * Supported intents:
 *  - "mau beli [produk]"   → find listing, initiate payment
 *  - "bayar [amount]"       → direct USYC payment
 *  - "cek saldo"            → wallet balance check
 *  - "daftar wallet"        → register Arc wallet
 *  - "confirm"              → release escrow
 *  - "cancel"               → cancel pending payment / refund escrow
 */
class PaymentAgent
{
    // Intent keywords (Indonesian)
    const INTENTS = [
        'buy'          => ['mau beli', 'ingin beli', 'order', 'pesan', 'beli ', 'ambil ', 'mau ambil', 'tertarik dengan', 'minat'],
        'direct_pay'   => ['bayar', 'transfer usyc', 'kirim usyc', 'bayar usyc'],
        'balance'      => ['cek saldo', 'saldo saya', 'saldo usyc', 'berapa saldo', 'lihat saldo'],
        'register'     => ['daftar wallet', 'buat wallet', 'register wallet', 'aktivasi wallet'],
        'confirm'      => ['confirm', 'sudah terima', 'barang sudah sampai', 'release escrow', 'oke bayar'],
        'cancel'       => ['cancel', 'batal', 'batalkan pembayaran', 'refund'],
        'topup'        => ['topup', 'top up', 'isi saldo', 'tambah saldo'],
    ];

    public function __construct(
        private ArcPaymentService $arcPayment,
        private WhacenterService  $wa,
    ) {}

    /**
     * Main entry — analyse a WhatsApp message and execute payment actions.
     * Returns true if an action was taken, false if not a payment-related message.
     */
    public function handle(Message $message): bool
    {
        $body    = strtolower(trim($message->body ?? $message->message ?? ''));
        $sender  = $message->sender_number ?? '';
        $groupId = $message->whatsapp_group_id;

        if (empty($body) || empty($sender)) {
            return false;
        }

        $intent = $this->detectIntent($body);

        if (!$intent) {
            return false;
        }

        Log::info('[PaymentAgent] Intent detected', [
            'intent' => $intent,
            'sender' => $sender,
            'body'   => substr($body, 0, 100),
        ]);

        $log = AgentLog::create([
            'agent_name'    => 'PaymentAgent',
            'input_payload' => [
                'message_id' => $message->id,
                'intent'     => $intent,
                'sender'     => $sender,
            ],
            'status'        => 'processing',
        ]);

        try {
            $result = match($intent) {
                'buy'        => $this->handleBuyIntent($message, $sender, $body, $groupId),
                'balance'    => $this->handleBalanceCheck($sender),
                'register'   => $this->handleWalletRegister($sender),
                'confirm'    => $this->handleEscrowConfirm($sender),
                'cancel'     => $this->handleCancelPayment($sender),
                'topup'      => $this->handleTopup($sender),
                default      => false,
            };

            $log->update(['status' => 'success', 'output_payload' => ['action' => $intent, 'result' => $result]]);
            return (bool) $result;
        } catch (\Exception $e) {
            Log::error('[PaymentAgent] Error', ['error' => $e->getMessage()]);
            $log->update(['status' => 'failed', 'output_payload' => ['error' => $e->getMessage()]]);
            $this->wa->sendMessage($sender, "❌ Terjadi kesalahan: {$e->getMessage()}");
            return false;
        }
    }

    // ── Intent Handlers ───────────────────────────────────────────────────────

    private function handleBuyIntent(Message $message, string $senderPhone, string $body, ?int $groupId): bool
    {
        // 1. Find the listing being referenced
        $listing = $this->findListingFromContext($body, $message, $groupId);

        if (!$listing) {
            $this->wa->sendMessage($senderPhone,
                "🔍 Saya tidak bisa menemukan iklan yang dimaksud.\n\n"
                . "Untuk membeli dengan USYC, balas langsung ke pesan iklan tersebut dan ketik:\n"
                . "_\"mau beli [nama barang]\"_\n\n"
                . "Atau cek saldo kamu dulu: _\"cek saldo\"_"
            );
            return false;
        }

        $sellerPhone = $listing->contact_number ?? $listing->contact?->phone_number;

        if (!$sellerPhone || $sellerPhone === $senderPhone) {
            $this->wa->sendMessage($senderPhone, "⚠️ Tidak bisa membeli iklan sendiri.");
            return false;
        }

        if (!$listing->accepts_usyc || !$listing->price_usyc) {
            // Offer to enable USYC
            $priceFormatted = $listing->price_formatted;
            $this->wa->sendMessage($senderPhone,
                "💡 *{$listing->title}*\n"
                . "Harga: {$priceFormatted}\n\n"
                . "Penjual belum mengaktifkan pembayaran USYC untuk iklan ini.\n"
                . "Hubungi penjual langsung atau minta mereka aktifkan pembayaran USYC.\n\n"
                . "Info: USYC adalah stablecoin dollar yang menghasilkan bunga 5% APY 📈"
            );
            return false;
        }

        $amountUsyc = (float) $listing->price_usyc;

        // 2. Check buyer wallet
        $buyerWallet = UsycWallet::forPhone($senderPhone);

        if (!$buyerWallet->hasSufficientBalance($amountUsyc)) {
            $available = $buyerWallet->formatted_balance;
            $this->wa->sendMessage($senderPhone,
                "💸 Saldo USYC tidak cukup!\n\n"
                . "📦 Harga: {$amountUsyc} USYC\n"
                . "💰 Saldo kamu: {$available}\n\n"
                . "Ketik _\"topup\"_ untuk mengisi saldo USYC via Circle."
            );
            return false;
        }

        // 3. Confirm intent before executing
        $fee     = round($amountUsyc * 0.001, 4);
        $total   = $amountUsyc + $fee;
        $contact = Contact::where('phone_number', $sellerPhone)->first();
        $seller  = $contact?->getSapaan() ?? $sellerPhone;

        $confirmMsg = "🛒 *Konfirmasi Pembayaran USYC*\n\n"
            . "📦 Produk: *{$listing->title}*\n"
            . "👤 Penjual: {$seller}\n"
            . "💵 Harga: {$amountUsyc} USYC (~\${$amountUsyc})\n"
            . "🔧 Fee (0.1%): {$fee} USYC\n"
            . "💳 Total: {$total} USYC\n\n"
            . "⏳ Dana masuk *escrow* 24 jam — dilepas ke penjual setelah kamu konfirmasi terima.\n"
            . "Selama escrow, dana menghasilkan *yield 5% APY* via USYC! 📈\n\n"
            . "Balas *\"confirm\"* untuk lanjutkan, atau *\"cancel\"* untuk batal.";

        $this->wa->sendMessage($senderPhone, $confirmMsg);

        // Store pending payment context in session
        $buyerWallet->update([
            'metadata' => array_merge($buyerWallet->metadata ?? [], [
                'pending_buy' => [
                    'listing_id'   => $listing->id,
                    'seller_phone' => $sellerPhone,
                    'amount_usyc'  => $amountUsyc,
                    'wa_message_id'=> $message->message_id ?? null,
                    'expires_at'   => now()->addMinutes(10)->toIso8601String(),
                ]
            ])
        ]);

        return true;
    }

    private function handleBalanceCheck(string $phone): bool
    {
        $balance = $this->arcPayment->getBalance($phone);
        $wallet  = UsycWallet::forPhone($phone);

        $msg = "💰 *Saldo Wallet USYC Kamu*\n\n"
            . "🏦 USYC: *{$balance['usyc']} USYC* (~\${$balance['usd_equiv']})\n"
            . "🔒 Reserved: {$balance['reserved']} USYC\n"
            . "✅ Tersedia: *{$balance['available']} USYC*\n\n"
            . "🔗 Arc Address:\n`{$balance['address']}`\n\n"
            . "📈 USYC menghasilkan ~5% APY otomatis via Circle\n"
            . "🌐 Network: {$balance['network']}";

        $this->wa->sendMessage($phone, $msg);
        return true;
    }

    private function handleWalletRegister(string $phone): bool
    {
        $wallet = $this->arcPayment->getOrCreateWallet($phone, topupDemo: true);

        $msg = "🎉 *Wallet USYC Berhasil Dibuat!*\n\n"
            . "🔗 *Arc Address:*\n`{$wallet->arc_address}`\n\n"
            . "💰 *Saldo Awal (Testnet):* 100 USYC (~\$100)\n\n"
            . "✨ *Apa itu USYC?*\n"
            . "USYC (USD Yield Coin) adalah stablecoin dollar yang menghasilkan bunga otomatis sekitar 5% APY. "
            . "Dana kamu bekerja sendiri, bahkan saat menunggu di escrow! 📈\n\n"
            . "⚡ *Cara beli di Marketplace Jamaah:*\n"
            . "1. Lihat iklan di grup\n"
            . "2. Ketik: _\"mau beli [nama barang]\"_\n"
            . "3. Konfirmasi dengan _\"confirm\"_\n"
            . "4. Selesai! Transaksi tercatat di Arc blockchain 🔗";

        $this->wa->sendMessage($phone, $msg);
        return true;
    }

    private function handleEscrowConfirm(string $phone): bool
    {
        $wallet  = UsycWallet::forPhone($phone);
        $pending = $wallet->metadata['pending_buy'] ?? null;

        if (!$pending) {
            // Check if there's a held escrow tx
            $tx = UsycTransaction::where('sender_phone', $phone)
                ->where('escrow_status', 'held')
                ->latest()
                ->first();

            if (!$tx) {
                $this->wa->sendMessage($phone,
                    "ℹ️ Tidak ada transaksi pending yang perlu dikonfirmasi."
                );
                return false;
            }

            // Release escrow
            $tx = $this->arcPayment->releaseEscrow($tx);
            $this->wa->sendMessage($phone,
                "✅ *Pembayaran Selesai!*\n\n"
                . "💵 {$tx->amount_usyc} USYC telah dikirim ke penjual.\n"
                . ($tx->yield_earned > 0 ? "📈 Yield selama escrow: +{$tx->yield_earned} USYC\n\n" : "\n")
                . "🔗 TX Hash: `{$tx->tx_hash}`"
            );
            return true;
        }

        // Execute the pending buy
        if (strtotime($pending['expires_at']) < time()) {
            $wallet->update(['metadata' => array_merge($wallet->metadata, ['pending_buy' => null])]);
            $this->wa->sendMessage($phone, "⏰ Sesi pembayaran sudah kadaluarsa. Silakan ulangi.");
            return false;
        }

        $tx = $this->arcPayment->createEscrow(
            senderPhone:   $phone,
            receiverPhone: $pending['seller_phone'],
            amountUsyc:    $pending['amount_usyc'],
            escrowHours:   24,
            context: [
                'listing_id'    => $pending['listing_id'],
                'wa_message_id' => $pending['wa_message_id'] ?? null,
            ]
        );

        // Clear pending
        $meta = $wallet->fresh()->metadata ?? [];
        unset($meta['pending_buy']);
        $wallet->update(['metadata' => $meta]);

        // Update listing payment status
        Listing::find($pending['listing_id'])?->update(['payment_status' => 'escrow_held']);

        $this->wa->sendMessage($phone,
            "✅ *Pembayaran USYC Berhasil!*\n\n"
            . "💵 {$tx->amount_usyc} USYC masuk *escrow* di Arc blockchain\n"
            . "🔗 TX: `{$tx->tx_hash}`\n\n"
            . "⏳ Dana akan otomatis dilepas ke penjual dalam 24 jam, "
            . "atau segera setelah kamu konfirmasi terima barang dengan ketik _\"confirm\"_.\n\n"
            . "📈 Selama menunggu, dana kamu terus menghasilkan yield USYC!"
        );

        // Notify seller
        $seller = Contact::where('phone_number', $pending['seller_phone'])->first();
        $listing = Listing::find($pending['listing_id']);
        if ($pending['seller_phone']) {
            $this->wa->sendMessage($pending['seller_phone'],
                "💰 *Pembayaran Diterima via USYC!*\n\n"
                . "🛍️ Produk: *{$listing?->title}*\n"
                . "💵 Jumlah: {$tx->amount_usyc} USYC (~\${$tx->amount_usyc})\n"
                . "🔒 Status: Dalam *escrow* 24 jam\n\n"
                . "Dana akan otomatis masuk ke wallet kamu setelah buyer konfirmasi atau 24 jam berlalu.\n"
                . "🔗 TX: `{$tx->tx_hash}`"
            );
        }

        return true;
    }

    private function handleCancelPayment(string $phone): bool
    {
        $wallet  = UsycWallet::forPhone($phone);
        $pending = $wallet->metadata['pending_buy'] ?? null;

        if ($pending) {
            $meta = $wallet->metadata ?? [];
            unset($meta['pending_buy']);
            $wallet->update(['metadata' => $meta]);
            $this->wa->sendMessage($phone, "❌ Pembayaran dibatalkan.");
            return true;
        }

        $this->wa->sendMessage($phone, "ℹ️ Tidak ada pembayaran yang perlu dibatalkan.");
        return false;
    }

    private function handleTopup(string $phone): bool
    {
        $msg = "💳 *Isi Saldo USYC*\n\n"
            . "USYC (USD Yield Coin) adalah stablecoin yang diterbitkan oleh *Circle* dan berjalan di *Arc blockchain*.\n\n"
            . "🔗 *Cara Top-up:*\n"
            . "1. Kunjungi: https://app.circle.com/usyc\n"
            . "2. Connect ke Arc wallet kamu\n"
            . "3. Tukarkan USD/IDR ke USYC\n"
            . "4. Kirim ke address wallet kamu\n\n";

        $wallet  = UsycWallet::forPhone($phone);
        $address = $wallet->arc_address ?? $this->arcPayment->getOrCreateWallet($phone)->arc_address;
        $msg .= "📋 *Arc Wallet Address kamu:*\n`{$address}`\n\n"
            . "💡 *Tip:* USYC terus menghasilkan yield ~5% APY secara otomatis!";

        $this->wa->sendMessage($phone, $msg);
        return true;
    }

    // ── Intent Detection ──────────────────────────────────────────────────────

    private function detectIntent(string $body): ?string
    {
        foreach (self::INTENTS as $intent => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($body, $kw)) {
                    return $intent;
                }
            }
        }
        return null;
    }

    /**
     * Find a listing referenced by the message — check quoted message, then keyword match.
     */
    private function findListingFromContext(string $body, Message $message, ?int $groupId): ?Listing
    {
        // 1. Try to find by quoted/replied message
        if ($message->reply_to_message_id ?? null) {
            $parentMsg = Message::where('message_id', $message->reply_to_message_id)->first();
            if ($parentMsg?->listing_id) {
                return Listing::find($parentMsg->listing_id);
            }
        }

        // 2. Extract product name from message body
        $productName = $this->extractProductName($body);

        if (!$productName) {
            return null;
        }

        // 3. Search active listings in the same group or globally
        $query = Listing::where('status', 'active')
            ->where('accepts_usyc', true);

        if ($groupId) {
            $query->where('whatsapp_group_id', $groupId);
        }

        return $query
            ->where(function ($q) use ($productName) {
                $q->where('title', 'ilike', "%{$productName}%")
                  ->orWhere('description', 'ilike', "%{$productName}%");
            })
            ->latest()
            ->first();
    }

    private function extractProductName(string $body): ?string
    {
        $buyKeywords = array_merge(...array_values(self::INTENTS));

        foreach (['mau beli ', 'ingin beli ', 'order ', 'pesan ', 'beli ', 'ambil '] as $prefix) {
            if (str_contains($body, $prefix)) {
                $pos  = strpos($body, $prefix) + strlen($prefix);
                $name = trim(substr($body, $pos));
                return strlen($name) > 2 ? $name : null;
            }
        }

        return null;
    }
}
