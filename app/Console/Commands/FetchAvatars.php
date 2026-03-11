<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\WhacenterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchAvatars extends Command
{
    protected $signature = 'contacts:fetch-avatars {--limit=50 : Max contacts to process}';
    protected $description = 'Fetch WhatsApp profile pictures for contacts without avatars';

    public function handle(WhacenterService $wa): int
    {
        $limit = (int) $this->option('limit');
        $contacts = Contact::whereNull('avatar')
            ->orWhere('avatar', '')
            ->orderByDesc('last_seen')
            ->limit($limit)
            ->pluck('phone_number')
            ->toArray();

        if (empty($contacts)) {
            $this->info('All contacts already have avatars.');
            return 0;
        }

        $this->info("Fetching profile pictures for " . count($contacts) . " contacts...");

        $results = $wa->getBulkProfilePics($contacts);

        $updated = 0;
        foreach ($results as $phone => $url) {
            if ($url) {
                Contact::where('phone_number', $phone)->update(['avatar' => $url]);
                $updated++;
                $this->line("  ✓ {$phone} → avatar saved");
            }
        }

        $this->info("Done. Updated {$updated}/" . count($contacts) . " avatars.");
        Log::info("FetchAvatars: updated {$updated}/" . count($contacts) . " avatars");

        return 0;
    }
}
