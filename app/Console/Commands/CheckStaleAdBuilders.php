<?php

namespace App\Console\Commands;

use App\Agents\AdBuilderAgent;
use App\Services\WhacenterService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckStaleAdBuilders extends Command
{
    protected $signature = 'ads:check-stale
                            {--threshold=10 : Idle minutes before stale prompt is sent}
                            {--grace=5 : Minutes to wait for user reply before auto-cancel}';

    protected $description = 'Find ad-builder sessions idle > threshold, prompt user to continue or cancel';

    public function handle(AdBuilderAgent $builder, WhacenterService $wa): int
    {
        $threshold = (int) $this->option('threshold');
        $grace     = (int) $this->option('grace');
        $phones    = $builder->getActivePhones();
        $now       = now();

        $stats = ['prompted' => 0, 'cancelled' => 0, 'pruned' => 0];

        foreach ($phones as $phone) {
            $state = $builder->getState($phone);
            if (!$state) {
                $builder->cancelSession($phone); // also unregisters
                $stats['pruned']++;
                continue;
            }

            $lastActivity = isset($state['last_activity_at'])
                ? Carbon::parse($state['last_activity_at'])
                : null;
            $promptSentAt = isset($state['stale_prompt_sent_at'])
                ? Carbon::parse($state['stale_prompt_sent_at'])
                : null;

            if ($promptSentAt) {
                if ($promptSentAt->diffInMinutes($now) >= $grace) {
                    try {
                        $wa->sendMessage($phone,
                            "⏰ *Sesi pembuatan iklan dibatalkan otomatis* karena tidak ada balasan dalam {$grace} menit.\n\n"
                            . "Ketik *buat iklan* kapan saja untuk memulai lagi 😊"
                        );
                    } catch (\Throwable $e) {
                        Log::warning("CheckStaleAdBuilders: failed to send cancel notice to {$phone}", ['error' => $e->getMessage()]);
                    }
                    $builder->cancelSession($phone);
                    $stats['cancelled']++;
                    $this->info("Auto-cancelled {$phone} (grace exceeded)");
                }
                continue;
            }

            if ($lastActivity && $lastActivity->diffInMinutes($now) >= $threshold) {
                try {
                    $wa->sendMessage($phone,
                        "⏰ *Sesi iklan masih berjalan?*\n\n"
                        . "Sudah {$threshold} menit tidak ada aktivitas. Mau dilanjutkan?\n\n"
                        . "Balas *lanjut* untuk teruskan, atau *batal* untuk membatalkan.\n\n"
                        . "_Jika tidak ada balasan dalam {$grace} menit, sesi akan dibatalkan otomatis._"
                    );
                    $builder->markStalePrompt($phone);
                    $stats['prompted']++;
                    $this->info("Stale prompt sent to {$phone}");
                } catch (\Throwable $e) {
                    Log::warning("CheckStaleAdBuilders: failed to prompt {$phone}", ['error' => $e->getMessage()]);
                }
            }
        }

        $this->info("Done. Prompted: {$stats['prompted']}, cancelled: {$stats['cancelled']}, pruned: {$stats['pruned']}");
        return self::SUCCESS;
    }
}
