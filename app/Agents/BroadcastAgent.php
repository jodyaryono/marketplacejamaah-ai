<?php

namespace App\Agents;

use App\Events\NewListingCreated;
use App\Events\NewMessageReceived;
use App\Models\AgentLog;
use App\Models\AnalyticsDaily;
use App\Models\Listing;
use App\Models\Message;
use App\Services\WhacenterService;

class BroadcastAgent
{
    public function __construct(
        private WhacenterService $whacenter
    ) {}

    public function handle(Message $message, ?Listing $listing = null): void
    {
        $start = microtime(true);
        $log = AgentLog::create([
            'agent_name' => 'BroadcastAgent',
            'message_id' => $message->id,
            'status' => 'processing',
        ]);

        try {
            // Update analytics
            $this->updateAnalytics($message);

            // Broadcast new message event (WebSocket)
            event(new NewMessageReceived($message));

            // Broadcast new listing event (WebSocket for dashboard + WA group notification)
            if ($listing) {
                event(new NewListingCreated($listing));
                $this->notifyGroup($listing, $message);
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->update(['status' => 'success', 'duration_ms' => $duration]);
        } catch (\Exception $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function notifyGroup(Listing $listing, Message $message): void
    {
        $group = $listing->group ?? optional($listing->message)->group;
        if (!$group) {
            return;
        }

        // Jika pesan asli dari master/owner langsung di WAG: jangan full-repost (duplikat),
        // tapi balas SINGKAT di grup yang sama dengan link halaman iklan supaya member lain
        // bisa langsung klik. DM ke master juga dikirim sebagai konfirmasi cepat.
        if (MasterCommandAgent::isMasterPhone($message->sender_number ?? '')) {
            $listingUrl = url('/p/' . $listing->id);
            try {
                $this->whacenter->sendGroupMessage(
                    $group->group_name,
                    "✅ *Iklan terdaftar di Marketplace Jamaah*\n\n📦 *{$listing->title}*\n🔗 {$listingUrl}"
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('BroadcastAgent: master WAG ack failed', ['error' => $e->getMessage()]);
            }
            try {
                $this->whacenter->sendMessage(
                    $message->sender_number,
                    "✅ *Iklan tayang!*\n\n📦 *{$listing->title}*\n🔗 {$listingUrl}\n\n_Edit kapan saja: ketik *edit #{$listing->id}*_"
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('BroadcastAgent: master DM confirmation failed', ['error' => $e->getMessage()]);
            }
            return;
        }

        $priceLabel   = $listing->price_formatted ?? 'Harga Nego';
        $listingUrl   = url('/p/' . $listing->id);
        $categoryLine = $listing->category ? "📂 {$listing->category->name}\n" : '';
        $locLine      = $listing->location ? "📍 {$listing->location}\n" : '';
        // Preserve the FULL member description — never truncate, never drop lines.
        // Rule from owner: boleh ditambah, tidak boleh dikurangi.
        $fullDesc     = trim((string) ($listing->description ?? ''));
        $descLine     = $fullDesc !== '' ? $fullDesc . "\n\n" : '';

        $caption = "🛍️ *{$listing->title}*\n\n"
            . $descLine
            . "💰 {$priceLabel}\n"
            . $categoryLine
            . $locLine
            . "\n🔗 {$listingUrl}";

        $mediaUrl = $listing->media_urls[0] ?? null;
        if ($mediaUrl) {
            $this->whacenter->sendGroupImageMessage($group->group_name, $caption, $mediaUrl);
        } else {
            $this->whacenter->sendGroupMessage($group->group_name, $caption);
        }
    }

    private function updateAnalytics(Message $message): void
    {
        $date = now()->toDateString();
        $groupId = $message->whatsapp_group_id;

        $analytics = AnalyticsDaily::firstOrCreate(
            ['date' => $date, 'whatsapp_group_id' => $groupId]
        );

        // Use increment to avoid full table recounts on every message
        $analytics->increment('total_messages');
        if ($message->is_ad) {
            $analytics->increment('total_ads');
        }
    }
}
