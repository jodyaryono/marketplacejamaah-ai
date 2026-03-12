<?php

namespace App\Console\Commands;

use App\Models\Listing;
use Illuminate\Console\Command;

class ExpireOldListings extends Command
{
    protected $signature = 'listings:expire {--days=30 : Expire listings older than this many days}';
    protected $description = 'Mark active listings older than N days as expired';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = Listing::where('status', 'active')
            ->where('created_at', '<', $cutoff)
            ->update(['status' => 'expired']);

        $this->info("Expired {$count} listings older than {$days} days.");
        return 0;
    }
}
