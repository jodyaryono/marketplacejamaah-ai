<?php

namespace App\Agents;

use App\Events\NewListingCreated;
use App\Events\NewMessageReceived;
use App\Models\AgentLog;
use App\Models\AnalyticsDaily;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Setting;
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

        $contact = \App\Models\Contact::where('phone_number', $message->sender_number)->first();
        $senderName = $contact ? $contact->getSapaan($message->sender_name) : ($message->sender_name ?? $message->sender_number);
        $categoryName = $listing->category?->name ?? 'Umum';
        $priceLabel = $listing->price_formatted ?? '-';
        $listingUrl = url('/p/' . $listing->id);
        $paddedId = str_pad($listing->id, 5, '0', STR_PAD_LEFT);
        $locationLine = $listing->location ? "\xF0\x9F\x93\x8D Lokasi: {$listing->location}\n" : '';

        $tpl = Setting::get('template_broadcast_new_listing', "✅ *Iklan Diterima!*\n\nHalo *{senderName}*, iklan kamu sudah masuk! 🎉\n\n📋 *{title}*\n💰 {priceLabel}");
        $text = str_replace(
            ['{senderName}', '{title}', '{categoryName}', '{priceLabel}', '{locationLine}', '{paddedId}', '{listingUrl}'],
            [$senderName, $listing->title ?? '', $categoryName, $priceLabel, $locationLine, $paddedId, $listingUrl],
            $tpl
        );

        $this->whacenter->sendGroupMessage($group->group_name, $text);
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
