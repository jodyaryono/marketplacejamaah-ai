<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\HadithService;
use App\Services\WhacenterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendDailyHadith extends Command
{
    protected $signature = 'hadith:send {--force : Send immediately regardless of schedule}';
    protected $description = 'Send a random hadith about trade/commerce to the Marketplace Jamaah group';

    public function handle(WhacenterService $wa): int
    {
        $enabled = Setting::get('hadith_enabled', 'true');
        if (!in_array($enabled, ['true', '1', 'yes'], true)) {
            $this->info('Hadith sending is disabled in settings.');
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->isScheduledNow()) {
            return self::SUCCESS;
        }

        $groupName = Setting::get('marketplace_name', 'Marketplace Jamaah');

        // Avoid repeats: keep a sliding window of recently-sent indices.
        // Window size ≈ 1/3 of total pool so distribution feels fresh but not exhaustive.
        $historySize = max(1, (int) floor(HadithService::count() / 3));
        $recent = (array) Cache::get('hadith:recent_indices', []);

        $hadith = HadithService::random($recent);
        $message = HadithService::formatForWhatsApp($hadith);

        try {
            $result = $wa->sendGroupMessage($groupName, $message);

            // Send donation appeal as a separate message so the link stays above
            // WhatsApp's "Read more" fold and remains tappable.
            sleep(2);
            try {
                $wa->sendGroupMessage($groupName, HadithService::donationAppeal());
            } catch (\Throwable $eDonation) {
                Log::warning('Daily hadith donation appeal failed to send', ['error' => $eDonation->getMessage()]);
            }

            $recent[] = $hadith['index'];
            if (count($recent) > $historySize) {
                $recent = array_slice($recent, -$historySize);
            }
            Cache::put('hadith:recent_indices', $recent, now()->addDays(30));
            Cache::put('hadith:last_index', $hadith['index'], now()->addDays(7)); // legacy

            Log::info('Daily hadith sent', [
                'theme' => $hadith['theme'],
                'index' => $hadith['index'],
                'group' => $groupName,
            ]);

            $this->info("Hadith sent: {$hadith['theme']}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('Failed to send daily hadith', [
                'error' => $e->getMessage(),
                'theme' => $hadith['theme'],
            ]);

            $this->error("Failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    /**
     * Check if the current time (Asia/Jakarta) matches any of the configured hadith_times.
     * Setting format: "05:45" or "05:45,18:00" (comma-separated HH:MM).
     */
    private function isScheduledNow(): bool
    {
        $times = Setting::get('hadith_times', '05:45');
        $now = now('Asia/Jakarta')->format('H:i');

        $scheduled = array_map('trim', explode(',', $times));

        return in_array($now, $scheduled, true);
    }
}
