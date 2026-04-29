<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\SystemMessage;
use App\Services\WhacenterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OnboardMissingMembers extends Command
{
    protected $signature = 'onboard:missing
                            {--group= : Group JID (default: from config)}
                            {--dry-run : List members to onboard without sending DMs}
                            {--delay=6 : Seconds between DMs to avoid rate-limiting (min 6)}
                            {--max-send=20 : Maximum number of DMs to send per run (default 20, max 50)}
                            {--force : Skip confirmation prompt for large batches}';

    protected $description = 'Find group members not yet onboarded and send them intro DMs';

    private const ACTIVE_STATUSES = ['pending', 'pending_seller_products', 'pending_buyer_products', 'pending_both_products', 'completed', 'skipped_legacy'];

    public function handle(WhacenterService $wa): int
    {
        $groupId = $this->option('group') ?: config('services.wa_gateway.group_id');
        $isDryRun = (bool) $this->option('dry-run');
        $delay = max(6, (int) $this->option('delay'));   // enforce minimum 6s gap
        $maxSend = min(50, max(1, (int) $this->option('max-send')));  // hard cap: 1–50
        $force = (bool) $this->option('force');

        if (!$groupId) {
            $this->error('No group ID provided. Use --group=xxx@g.us or set services.wa_gateway.group_id in config.');
            return 1;
        }

        if (!$isDryRun) {
            $this->warn("⚠  Will send at most {$maxSend} DM(s) with {$delay}s delay between each.");
            if ($maxSend > 10 && !$force) {
                if (!$this->confirm("Sending {$maxSend} DMs in one run. Continue?", false)) {
                    $this->info('Aborted.');
                    return 0;
                }
            }
        }

        $this->info("Fetching group members for {$groupId}...");
        $members = $wa->getGroupMembers($groupId);

        if (empty($members)) {
            $this->error('No group members returned. Check gateway connection.');
            return 1;
        }

        $this->info('Total group members: ' . count($members));

        // Load registered/active phone numbers in one query
        $registeredPhones = Contact::where('is_registered', true)->pluck('phone_number', 'phone_number')->toArray();
        $activePhones = Contact::whereIn('onboarding_status', self::ACTIVE_STATUSES)
            ->whereNotNull('onboarding_status')
            ->pluck('phone_number', 'phone_number')
            ->toArray();

        $stats = ['sent' => 0, 'skip_registered' => 0, 'skip_active' => 0, 'skip_lid' => 0, 'errors' => 0];
        $groupName = 'Marketplace Jamaah';

        foreach ($members as $member) {
            $phone = $member['number'] ?? '';
            if (empty($phone)) {
                continue;
            }

            // Skip LID numbers (14+ digits, not starting with 62)
            if (preg_match('/^\d{14,}$/', $phone) && !str_starts_with($phone, '62')) {
                $this->line("  ⚠ SKIP LID:  {$phone}");
                $stats['skip_lid']++;
                continue;
            }

            if (isset($registeredPhones[$phone])) {
                $stats['skip_registered']++;
                continue;
            }

            if (isset($activePhones[$phone])) {
                $this->line("  ↻ SKIP onboarding-in-progress: {$phone}");
                $stats['skip_active']++;
                continue;
            }

            // Member needs onboarding
            $contact = Contact::firstOrCreate(['phone_number' => $phone]);
            $name = $contact->name;
            $roleDisplay = $contact->member_role ? " [{$contact->member_role}]" : '';

            if ($isDryRun) {
                $this->line("  → WOULD ONBOARD: {$phone} | {$name}{$roleDisplay}");
                $stats['sent']++;
                continue;
            }

            // Hard cap: stop once limit reached
            if ($stats['sent'] >= $maxSend) {
                $this->warn("  ⛔ Reached --max-send={$maxSend} limit. Run again to continue.");
                break;
            }

            $this->line("  → Sending onboarding DM to: {$phone} | {$name}{$roleDisplay}");

            try {
                $greeting = "Assalamu'alaikum wa rahmatullahi wa barakatuh! 🙏";
                $intro = "{$greeting}\n\n"
                    . 'Perkenalkan, saya *Admin Marketplace Jamaah* — komunitas jual beli sesama Muslim yang amanah dan berkah. '
                    . "Alhamdulillah senang ada anggota baru, semoga jadi ladang berkah ya 😊\n\n"
                    . 'Eh, saya belum kenal nih — boleh tau nama Kakak siapa? 😊';

                $wa->sendMessage($phone, $intro);
                $contact->update(['onboarding_status' => 'completed', 'is_registered' => true]);
                $stats['sent']++;

                Log::info("OnboardMissingMembers: sent DM to {$phone}");

                if ($delay > 0) {
                    sleep($delay);
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Failed for {$phone}: " . $e->getMessage());
                Log::error("OnboardMissingMembers: failed for {$phone}", ['error' => $e->getMessage()]);
                $stats['errors']++;
            }
        }

        $action = $isDryRun ? 'Would send' : 'Sent';
        $this->newLine();
        $this->info("Done.");
        $this->table(
            ['Metric', 'Count'],
            [
                [$action . ' onboarding DMs', $stats['sent']],
                ['Skipped (registered)', $stats['skip_registered']],
                ['Skipped (in-progress)', $stats['skip_active']],
                ['Skipped (LID/unresolvable)', $stats['skip_lid']],
                ['Errors', $stats['errors']],
            ]
        );

        return 0;
    }
}
