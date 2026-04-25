<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

class ApproveAllPending extends Command
{
    protected $signature = 'members:approve-pending {--dry-run}';

    protected $description = 'Bulk-approve all contacts stuck at onboarding_status=pending (mark as registered + completed)';

    public function handle(): int
    {
        $query = Contact::where('onboarding_status', 'pending')->where('is_blocked', false);
        $count = $query->count();

        if ($count === 0) {
            $this->info('Tidak ada kontak pending. Semua sudah bersih.');
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->warn("DRY RUN: akan approve {$count} kontak.");
            $query->limit(20)->get(['phone_number', 'name'])->each(function ($c) {
                $this->line("  • {$c->phone_number} — {$c->name}");
            });
            if ($count > 20) {
                $this->line('  ... dan ' . ($count - 20) . ' lainnya');
            }
            return 0;
        }

        $affected = $query->update([
            'onboarding_status' => 'completed',
            'is_registered' => true,
        ]);

        $this->info("✅ Berhasil approve {$affected} kontak.");
        return 0;
    }
}
