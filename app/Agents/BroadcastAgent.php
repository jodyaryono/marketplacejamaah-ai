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

        $priceLabel   = $listing->price_formatted ?? 'Harga Nego';
        $listingUrl   = url('/p/' . $listing->id);
        $categoryLine = $listing->category ? "📂 {$listing->category->name}\n" : '';
        $locLine      = $listing->location ? "📍 {$listing->location}\n" : '';
        $shortDesc    = self::extractWagDescription($listing->description ?? '');
        $descLine     = $shortDesc ? "_{$shortDesc}_\n" : '';

        $caption = "🛍️ *{$listing->title}*\n"
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

    /**
     * Extract the AI-generated description for WAG captions.
     * Prefers the [Analisis Gambar]: section; falls back to the longest paragraph.
     */
    public static function extractWagDescription(string $desc, int $limit = 200): string
    {
        if (empty($desc)) return '';

        // Prefer the AI-analysis section
        if (preg_match('/\[Analisis Gambar\]:\s*(.+)/si', $desc, $m)) {
            return \Illuminate\Support\Str::limit(trim($m[1]), $limit);
        }

        // Find the longest paragraph — AI descriptions tend to be longer
        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split('/\n+/', $desc)),
            fn($p) => mb_strlen($p) > 40
        ));

        if (!empty($paragraphs)) {
            usort($paragraphs, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
            return \Illuminate\Support\Str::limit($paragraphs[0], $limit);
        }

        return \Illuminate\Support\Str::limit(trim($desc), $limit);
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
