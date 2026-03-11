<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMessageJob;
use App\Models\Contact;
use App\Models\Message;
use App\Services\WhacenterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecoverMissedMessages extends Command
{
    protected $signature = 'wa:recover
        {--minutes=30 : Look back this many minutes for missed messages}
        {--dry : Show what would be recovered without actually processing}';

    protected $description = 'After gateway crash/restart, find and reply to DM messages that were missed';

    public function handle(WhacenterService $wa): int
    {
        $minutes = (int) $this->option('minutes');
        $isDry = $this->option('dry');

        $this->info("🔍 Scanning for unreplied DMs in the last {$minutes} minutes...");

        // Step 1: Find all contacts who sent DMs recently
        $cutoff = now()->subMinutes($minutes);

        // Get incoming DMs that have no outgoing reply after them
        $unrepliedContacts = $this->findUnrepliedDMs($cutoff);

        if ($unrepliedContacts->isEmpty()) {
            $this->info('✅ No missed messages found. All DMs have been replied to.');
            return 0;
        }

        $this->info("⚠️  Found {$unrepliedContacts->count()} contact(s) with unreplied DMs:");
        $this->newLine();

        foreach ($unrepliedContacts as $item) {
            $this->line("  📱 {$item['contact_name']} ({$item['phone']}) — \"{$item['last_message']}\" ({$item['sent_at']})");
        }

        $this->newLine();

        if ($isDry) {
            $this->warn('🏁 Dry run — no messages processed.');
            return 0;
        }

        // Step 2: For each unreplied contact, fetch their latest messages from gateway
        // and re-process the last unanswered one
        $recovered = 0;
        $gatewayUrl = rtrim(config('services.wa_gateway.url'), '/');
        $token = config('services.wa_gateway.token');
        $phoneId = config('services.wa_gateway.phone_id');

        foreach ($unrepliedContacts as $item) {
            try {
                $phone = $item['phone'];
                $lastInMsg = $item['message'];

                // Message is in our DB — re-dispatch it
                if ($lastInMsg) {
                    if ($lastInMsg->is_processed) {
                        // Processed but no reply sent (gateway was down when replying)
                        $lastInMsg->update(['is_processed' => false]);
                    }
                    // Either previously processed with no reply, or stuck unprocessed
                    ProcessMessageJob::dispatch($lastInMsg->id)->onQueue('agents');
                    $this->info("  ↻ Re-queued message #{$lastInMsg->id} for {$phone}");
                    $recovered++;
                    continue;
                }

                // Message not in DB — try to fetch from gateway
                $chatJid = $phone . '@c.us';
                $response = Http::timeout(15)->get("{$gatewayUrl}/fetch-messages", [
                    'phone_id' => $phoneId,
                    'token' => $token,
                    'chat_id' => $chatJid,
                    'limit' => 10,
                ]);

                if (!$response->successful()) {
                    $this->warn("  ⚠ Could not fetch messages for {$phone}: " . $response->body());
                    continue;
                }

                $data = $response->json();
                $fetchedMsgs = $data['messages'] ?? [];

                // Find messages that aren't in our DB yet
                $newCount = 0;
                foreach ($fetchedMsgs as $gMsg) {
                    if (empty($gMsg['message_id']) || empty($gMsg['message']))
                        continue;
                    if ($gMsg['from_me'] ?? false)
                        continue;

                    // Check if already in DB
                    if (Message::where('message_id', $gMsg['message_id'])->exists())
                        continue;

                    // Build payload like a webhook would send
                    $senderNum = preg_replace('/@\S+/', '', $gMsg['sender'] ?? $gMsg['from'] ?? '');
                    if (preg_match('/^0\d{8,12}$/', $senderNum)) {
                        $senderNum = '62' . substr($senderNum, 1);
                    }

                    $sentAt = isset($gMsg['timestamp'])
                        ? \Carbon\Carbon::createFromTimestamp($gMsg['timestamp'])
                        : now();

                    // Only process messages within our time window
                    if ($sentAt->lt($cutoff))
                        continue;

                    // Create the message in DB
                    $message = Message::create([
                        'message_id' => $gMsg['message_id'],
                        'sender_number' => $senderNum,
                        'sender_name' => $gMsg['sender_name'] ?? null,
                        'message_type' => $gMsg['type'] === 'conversation' ? 'text' : ($gMsg['type'] ?? 'text'),
                        'raw_body' => $gMsg['message'],
                        'is_processed' => false,
                        'sent_at' => $sentAt,
                        'direction' => 'in',
                        'raw_payload' => $gMsg,
                    ]);

                    // Dispatch for processing
                    ProcessMessageJob::dispatch($message->id)->onQueue('agents');
                    $this->info("  ✉ Recovered message #{$message->id} from {$senderNum}: \"{$gMsg['message']}\"");
                    $newCount++;
                    $recovered++;
                }

                if ($newCount === 0) {
                    $this->line("  — No new messages found for {$phone} from gateway");
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error recovering for {$phone}: {$e->getMessage()}");
                Log::error("RecoverMissedMessages: error for {$phone}", ['error' => $e->getMessage()]);
            }
        }

        $this->newLine();
        $this->info("🏁 Recovery complete: {$recovered} message(s) re-queued for processing.");

        Log::info("RecoverMissedMessages: recovered {$recovered} messages", [
            'contacts' => $unrepliedContacts->count(),
            'minutes_back' => $minutes,
        ]);

        return 0;
    }

    /**
     * Find contacts who sent DMs but never got a reply.
     */
    private function findUnrepliedDMs(\Carbon\Carbon $cutoff): \Illuminate\Support\Collection
    {
        // Get all incoming DMs since cutoff
        $incomingDMs = Message::whereNull('whatsapp_group_id')
            ->where('direction', 'in')
            ->where('sent_at', '>=', $cutoff)
            ->where('sender_number', '!=', 'bot')
            ->orderBy('sent_at', 'desc')
            ->get();

        // Group by sender, get the last message per contact
        $bySender = $incomingDMs->groupBy('sender_number');

        $unreplied = collect();

        foreach ($bySender as $phone => $messages) {
            $lastIn = $messages->first();  // most recent incoming

            // Check if there's any outgoing reply AFTER this incoming message
            $hasReply = Message::whereNull('whatsapp_group_id')
                ->where('direction', 'out')
                ->where('recipient_number', $phone)
                ->where('sent_at', '>', $lastIn->sent_at)
                ->exists();

            if (!$hasReply) {
                $contact = Contact::where('phone_number', $phone)->first();
                $unreplied->push([
                    'phone' => $phone,
                    'contact_name' => $contact->name ?? $phone,
                    'last_message' => mb_substr($lastIn->raw_body ?? '[media]', 0, 60),
                    'sent_at' => $lastIn->sent_at->format('H:i'),
                    'message' => $lastIn,
                ]);
            }
        }

        return $unreplied;
    }
}
