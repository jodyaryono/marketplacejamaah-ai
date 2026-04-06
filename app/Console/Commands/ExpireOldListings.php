<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Services\WhacenterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireOldListings extends Command
{
    protected $signature = 'listings:expire
                            {--days=30 : Expire listings older than this many days}
                            {--dry-run : Show what would expire without making changes}';
    protected $description = 'Mark active listings older than N days as expired and notify sellers';

    public function handle(WhacenterService $whacenter): int
    {
        $days    = (int) $this->option('days');
        $dryRun  = $this->option('dry-run');
        $cutoff  = now()->subDays($days);
        $baseUrl = rtrim(config('app.url'), '/');

        // Load listings that are about to expire (with contact for phone lookup)
        $expiring = Listing::with('contact')
            ->where('status', 'active')
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($expiring->isEmpty()) {
            $this->info('No listings to expire.');
            return 0;
        }

        if ($dryRun) {
            $this->info("DRY RUN — {$expiring->count()} listings would be expired:");
            foreach ($expiring as $l) {
                $this->line("  #{$l->id} {$l->title} (seller: {$l->contact_number})");
            }
            return 0;
        }

        // Bulk update status
        $ids = $expiring->pluck('id')->all();
        Listing::whereIn('id', $ids)->update(['status' => 'expired']);

        $this->info("Expired {$expiring->count()} listings older than {$days} days.");

        // Notify each unique seller once (group multiple listings in one DM)
        $bySeller = $expiring->groupBy(fn($l) => $l->contact_number ?? $l->contact?->phone_number);

        $notified = 0;
        $delay    = 0; // stagger sends by 3 s each to respect gateway rate limits

        foreach ($bySeller as $phone => $sellerListings) {
            if (empty($phone)) {
                continue;
            }

            $count  = $sellerListings->count();
            $name   = $sellerListings->first()->contact?->name ?? 'Kak';

            if ($count === 1) {
                $listing = $sellerListings->first();
                $link    = "{$baseUrl}/p/{$listing->id}";
                $msg     = "📭 *Iklan Kamu Telah Kadaluarsa*\n\n"
                    . "Halo *{$name}*, iklan berikut sudah otomatis diarsipkan karena sudah _{$days} hari_ tidak diperbarui:\n\n"
                    . "📦 *{$listing->title}* (#{$listing->id})\n"
                    . "🔗 {$link}\n\n"
                    . "Untuk mengaktifkan kembali, kirim pesan:\n"
                    . "*aktifkan #{$listing->id}*\n\n"
                    . "_Atau pasang iklan baru kapan saja di grup Marketplace Jamaah. Semoga rezekinya lancar! 🙏_";
            } else {
                $listingLines = $sellerListings->map(
                    fn($l) => "• *{$l->title}* (#{$l->id}) — {$baseUrl}/p/{$l->id}"
                )->implode("\n");

                $idList = $sellerListings->pluck('id')->map(fn($id) => "#$id")->implode(', ');

                $msg = "📭 *{$count} Iklan Kamu Telah Kadaluarsa*\n\n"
                    . "Halo *{$name}*, iklan berikut sudah otomatis diarsipkan karena _{$days} hari_ tidak diperbarui:\n\n"
                    . $listingLines . "\n\n"
                    . "Untuk mengaktifkan kembali, kirim:\n"
                    . "*aktifkan #<nomor iklan>*\n\n"
                    . "_Semoga rezekinya lancar! 🙏_";
            }

            try {
                if ($delay > 0) {
                    sleep((int) ceil($delay / 1000));
                }
                $whacenter->sendMessage($phone, $msg);
                $notified++;
                $delay += 3000; // 3 s between notifications
            } catch (\Exception $e) {
                Log::warning("ExpireOldListings: gagal kirim notif ke {$phone}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Notified {$notified} / {$bySeller->count()} sellers.");
        return 0;
    }
}
