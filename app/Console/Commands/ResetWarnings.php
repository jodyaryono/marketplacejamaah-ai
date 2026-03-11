<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetWarnings extends Command
{
    protected $signature = 'warnings:reset {--dry : Show what would be reset without changing anything}';
    protected $description = 'Reset warning_count to 0 for contacts whose last warning is older than the configured reset period';

    public function handle(): int
    {
        $days = (int) Setting::get('warning_reset_days', 1);
        if ($days < 1) $days = 1;

        $cutoff = now()->subDays($days);
        $isDry = $this->option('dry');

        $contacts = Contact::where('warning_count', '>', 0)
            ->where(function ($q) use ($cutoff) {
                $q->where('last_warning_at', '<=', $cutoff)
                  ->orWhereNull('last_warning_at');
            })
            ->get();

        if ($contacts->isEmpty()) {
            $this->info("✅ Tidak ada peringatan yang perlu direset (threshold: {$days} hari).");
            return self::SUCCESS;
        }

        foreach ($contacts as $contact) {
            $label = $contact->name ?: $contact->phone_number;
            $lastWarn = $contact->last_warning_at?->format('d/m/Y H:i') ?? 'tidak ada';

            if ($isDry) {
                $this->line("🔍 [DRY] {$label} — warning_count: {$contact->warning_count}, last_warning: {$lastWarn}");
            } else {
                $oldCount = $contact->warning_count;
                $contact->update([
                    'warning_count' => 0,
                    'is_blocked' => false,
                ]);
                $this->line("♻️ {$label} — warning_count: {$oldCount} → 0, unblocked, last_warning: {$lastWarn}");
                Log::info("warnings:reset — reset {$label}", [
                    'contact_id' => $contact->id,
                    'old_warning_count' => $oldCount,
                    'last_warning_at' => $contact->last_warning_at,
                ]);
            }
        }

        $action = $isDry ? 'akan direset' : 'direset';
        $this->info("✅ {$contacts->count()} kontak {$action} (threshold: {$days} hari).");

        return self::SUCCESS;
    }
}
