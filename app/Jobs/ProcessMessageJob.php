<?php

namespace App\Jobs;

use App\Agents\AdClassifierAgent;
use App\Agents\BotQueryAgent;
use App\Agents\BroadcastAgent;
use App\Agents\DataExtractorAgent;
use App\Agents\GroupAdminReplyAgent;
use App\Agents\ImageAnalyzerAgent;
use App\Agents\MasterCommandAgent;
use App\Agents\MemberOnboardingAgent;
use App\Agents\MessageModerationAgent;
use App\Agents\MessageParserAgent;
use App\Models\Message;
use App\Services\WhacenterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private int $messageId
    ) {}

    public function handle(
        MessageParserAgent $parser,
        AdClassifierAgent $classifier,
        MessageModerationAgent $moderator,
        DataExtractorAgent $extractor,
        ImageAnalyzerAgent $imageAnalyzer,
        BroadcastAgent $broadcaster,
        GroupAdminReplyAgent $groupAdmin,
        MemberOnboardingAgent $onboarding,
        BotQueryAgent $botQuery,
        MasterCommandAgent $master
    ): void {
        $message = Message::find($this->messageId);

        if (!$message) {
            Log::warning("ProcessMessageJob: Message {$this->messageId} not found");
            return;
        }

        if ($message->is_processed) {
            return;
        }

        try {
            // ── Master command: perintah dari nomor owner diproses sebelum semua pipeline ──
            $masterForwarded = false;
            if (MasterCommandAgent::isMaster($message)) {
                // Forwarded message from master DM → inject into ad pipeline as WAG message
                $isForwarded = $message->raw_payload['isForwarded'] ?? false;
                if ($isForwarded && is_null($message->whatsapp_group_id)) {
                    $mainGroup = \App\Models\WhatsappGroup::where('is_active', true)->first();
                    if ($mainGroup) {
                        $message->update(['whatsapp_group_id' => $mainGroup->id]);
                        $message->refresh();
                        $masterForwarded = true;
                        Log::info("ProcessMessageJob: master forwarded ad → assigned to group {$mainGroup->group_name}");
                        // Fall through to group ad pipeline below
                    } else {
                        app(WhacenterService::class)->sendMessage(
                            config('services.wa_gateway.master_phone'),
                            '⚠️ Tidak ada grup aktif untuk mencatatkan iklan forwarded.'
                        );
                        $message->update(['is_processed' => true, 'processed_at' => now()]);
                        return;
                    }
                } else {
                    $master->handle($message);
                    $message->update(['is_processed' => true, 'processed_at' => now()]);
                    return;
                }
            }

            // Silently ignore messages from blocked contacts (3-warning threshold reached)
            $senderContact = \App\Models\Contact::where('phone_number', $message->sender_number)->first();
            if ($senderContact?->is_blocked) {
                Log::info("ProcessMessageJob: ignoring blocked contact {$message->sender_number}");
                $message->update(['is_processed' => true, 'processed_at' => now()]);
                return;
            }

            // ── Instant delete: detect "Delete Me" command ──────────────
            if ($this->shouldAutoDelete($message)) {
                $this->executeAutoDelete($message);
                return;
            }

            // Direct message (no group) — onboarding or bot query
            if (is_null($message->whatsapp_group_id)) {
                $handled = $onboarding->handleDirectMessage($message);
                if (!$handled) {
                    $contact = \App\Models\Contact::where('phone_number', $message->sender_number)->first();
                    if ($contact) {
                        // Known contact (group member, registered or not) → AI replies contextually
                        $handled = $botQuery->handle($message);
                        if (!$handled) {
                            // BotQueryAgent failed — send polite fallback
                            $name = $contact->name ?? 'Kak';
                            app(\App\Services\WhacenterService::class)->sendMessage(
                                $message->sender_number,
                                "Aduh maaf *{$name}*, aku kurang nangkep maksudnya nih 🙏 Bisa cerita lebih lengkap?"
                            );
                        }
                    } else {
                        // Unknown contact (may be LID number or never-in-group) — still try BotQueryAgent.
                        // BotQueryAgent works without a Contact record; for help/search queries it works fine.
                        // Only fall back to WAG redirect if BotQueryAgent also fails.
                        $handled = $botQuery->handle($message);
                        if (!$handled) {
                            $wa = app(\App\Services\WhacenterService::class);
                            $wagLink = \App\Models\Setting::get('whatsapp_group_link', '');
                            $joinMsg = \App\Models\SystemMessage::getText(
                                'bot.unregistered_dm',
                                ['wag_link' => $wagLink],
                                "Halo! 👋\n\nKamu belum terdaftar di *Marketplace Jamaah*.\n\n"
                                    . "Untuk menggunakan fitur bot ini, silakan bergabung ke WhatsApp Group kami terlebih dahulu:\n"
                                    . ($wagLink ? $wagLink : '👉 Hubungi admin untuk mendapatkan link grup.')
                                    . "\n\n_Setelah bergabung dan mendaftar di grup, semua fitur bot (cari produk, lihat iklan, dll.) bisa kamu gunakan._ 🙏"
                            );
                            $wa->sendMessage($message->sender_number, $joinMsg);
                            Log::info("ProcessMessageJob: unknown DM from {$message->sender_number}, sent WAG join redirect");
                        }
                    }
                }
                $message->update(['is_processed' => true, 'processed_at' => now()]);
                return;  // DMs are not processed through the ad/moderation pipeline
            }

            // Step 1: Member onboarding check — send registration DM if new contact
            if (!$masterForwarded) {
                $onboarding->handleGroupMessage($message);
            }

            // Step 2: Detect @label prefix — labeled content (edukasi, info, pengumuman, dll)
            // is explicitly allowed in the group without needing to be an ad.
            $labeledCategory = $this->detectLabeledMessage($message);
            if ($labeledCategory) {
                $message->update([
                    'is_ad' => false,
                    'is_processed' => true,
                    'processed_at' => now(),
                    'message_category' => $labeledCategory,
                ]);
                $broadcaster->handle($message, null);
                $this->acknowledgeLabel($message, $labeledCategory);
                return;
            }

            // Step 3: Parse message structure
            $parsed = $parser->handle($message);

            // Step 4: Classify if ad
            $classification = $classifier->handle($message, $parsed);

            // Step 5: Moderate — categorise & detect violations (all non-ad messages)
            $moderation = $masterForwarded ? ['is_violation' => false] : $moderator->handle($message);

            $listing = null;

            // Only run the ad pipeline when is_ad=true AND no violation detected
            // (scam messages mis-classified as ads should not be extracted as listings)
            $isAd = $classification['is_ad'] ?? false;
            $isViolation = $moderation['is_violation'] ?? false;

            if ($isAd && !$isViolation) {
                // Step 6pre: Check if the same sender already has a recent listing
                // (e.g. created from image-only) — merge text into it instead of
                // creating a duplicate listing.
                // Only merge for text-only messages. If the new message has its own image,
                // it is a different product (e.g. seller posts two products in a row) and
                // must always create a separate listing.
                if (!$message->media_url) {
                    $listing = $this->tryMergeIntoExistingListing($message, $extractor);
                }

                if (!$listing) {
                    // Step 6: Extract structured listing data (new listing)
                    $listing = $extractor->handle($message);
                }

                if ($listing) {
                    // Step 6a: Analyze image if this message itself has media
                    if ($message->media_url) {
                        $imageAnalyzer->handle($message, $listing);
                    }

                    // Step 6b: Find companion image message (same sender, same group, ±15 min)
                    $this->mergeCompanionMedia($message, $listing, $imageAnalyzer);
                }
            } elseif (!$isAd && in_array($message->message_type, ['image', 'video', 'document', 'audio']) && $message->whatsapp_group_id) {
                // Step 6b: Image/video/doc/audio — try to attach to a companion text ad
                // Works whether or not media_url is saved (forwarded photos may have null media_url)
                $attached = $this->tryAttachImageToCompanionListing($message, $imageAnalyzer);
                if (!$attached) {
                    if ($message->media_url) {
                        $listing = $this->tryCreateListingFromImageOnly($message, $imageAnalyzer, $extractor);
                    } else {
                        // Media received but not downloadable — delete from group + ask sender to resend
                        if (!$masterForwarded) {
                            $this->deleteNonAdGroupMessage($message);
                            $this->sendImageClarificationDm($message, $message->message_type === 'video' ? 'video' : 'image');
                        }
                        $message->update(['is_processed' => true, 'processed_at' => now()]);
                        return;
                    }
                }
            } elseif (!$isAd && !$message->media_url && $message->whatsapp_group_id) {
                // Step 6c: Text-only follow-up (e.g. price message) — merge into companion listing
                $merged = $this->tryMergeTextIntoCompanionListing($message);
                if ($merged) {
                    $listing = $merged;
                }
            }

            // Step 7: Auto-delete non-ad group messages (not a violation, no listing created).
            // Violations are already handled by GroupAdminReplyAgent (with their own DM/warning).
            // Refresh is_ad from DB — tryAttachImageToCompanionListing or tryCreateListingFromImageOnly
            // may have updated it directly on the model after AdClassifier's initial decision.
            $message->refresh();
            $isAd = $message->is_ad ?? false;
            if (!$listing && !$isAd && !$isViolation && $message->whatsapp_group_id && !$masterForwarded) {
                $this->deleteNonAdGroupMessage($message);
            }

            // Gap: ad detected but listing extraction failed → try companion attach for images/videos first
            if ($isAd && !$listing && !$isViolation && $message->whatsapp_group_id) {
                if (in_array($message->message_type, ['image', 'video'])) {
                    // Image/video classified as ad but no listing extracted — likely a companion
                    // photo sent right after a text ad. Try attaching to that listing first.
                    $attached = $this->tryAttachImageToCompanionListing($message, $imageAnalyzer);
                    if (!$attached) {
                        $this->sendAdExtractionFailedDm($message);
                    }
                } else {
                    $this->sendAdExtractionFailedDm($message);
                }
            }

            // Step 8: Broadcast analytics + WebSocket events
            $broadcaster->handle($message, $listing);

            // Step 9: Send WA group/DM replies (confirmation & violation warnings)
            if ($masterForwarded) {
                // For master-forwarded ads, send result summary to master instead
                $masterPhone = config('services.wa_gateway.master_phone');
                $wa = app(WhacenterService::class);
                if ($listing) {
                    $wa->sendMessage($masterPhone, "✅ Iklan forwarded berhasil dicatat!\n\n📋 *{$listing->title}*\n💰 {$listing->price_formatted}\n🏷️ {$listing->category?->name}");
                } else {
                    $wa->sendMessage($masterPhone, "⚠️ Pesan forwarded diterima tapi tidak terdeteksi sebagai iklan. Pastikan pesan berisi iklan lengkap (nama barang, harga, kontak).");
                }
            } else {
                $groupAdmin->handle($message, $listing, $moderation);
            }

            // Mark message as processed after full pipeline completes
            $message->update(['is_processed' => true, 'processed_at' => now()]);
        } catch (\Exception $e) {
            Log::error("ProcessMessageJob failed for message {$this->messageId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessMessageJob permanently failed for message {$this->messageId}", [
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Detect @label prefix in a group message.
     * Labels allow non-ad content (education, info, announcements) to remain in the group.
     * Returns the category string if labeled, or null if normal message.
     *
     * Supported labels:
     *   @education / @edukasi   → educational content
     *   @info / @informasi      → general info
     *   @pengumuman / @announce → announcements
     *   @diskusi / @tanya       → discussion / questions
     *   @berita                 → news
     */
    private function detectLabeledMessage(Message $message): ?string
    {
        $body = ltrim($message->raw_body ?? '');
        if (!$body || !str_starts_with($body, '@')) {
            return null;
        }

        $lower = strtolower($body);
        return match (true) {
            str_starts_with($lower, '@education') || str_starts_with($lower, '@edukasi') => 'education',
            str_starts_with($lower, '@info') || str_starts_with($lower, '@informasi') => 'info',
            str_starts_with($lower, '@pengumuman') || str_starts_with($lower, '@announce') => 'announcement',
            str_starts_with($lower, '@diskusi') || str_starts_with($lower, '@tanya') => 'discussion',
            str_starts_with($lower, '@berita') => 'news',
            default => null,
        };
    }

    /**
     * Delete a non-ad, non-violation group message and DM the sender explaining:
     * - Their message was removed (group is for ads only)
     * - How to post allowed non-ad content using @label format
     */
    private function deleteNonAdGroupMessage(Message $message): void
    {
        $wa = app(WhacenterService::class);

        // 0. Backup media URL before deletion — retain for recovery
        if ($message->media_url) {
            Log::warning("ProcessMessageJob: BACKUP before delete — message {$message->id} media_url={$message->media_url} sender={$message->sender_number}");
        }

        // 1. Delete the message from the group
        $payload = $message->raw_payload ?? [];
        $messageKey = $payload['_key'] ?? null;
        try {
            if ($messageKey) {
                $wa->deleteMessage($messageKey);
            } elseif ($message->message_id) {
                $groupJid = $payload['group_id'] ?? $payload['from_group'] ?? null;
                if ($groupJid) {
                    $wa->deleteGroupMessage($groupJid, $message->message_id, $message->sender_number);
                }
            }
        } catch (\Exception $e) {
            Log::warning('ProcessMessageJob: deleteNonAdGroupMessage delete failed', ['error' => $e->getMessage()]);
        }

        $message->update(['message_category' => 'non_ad_deleted']);

        // 2. DM the sender — but only once per 30 minutes to avoid spamming them
        $recentDm = \App\Models\Message::where('direction', 'out')
            ->where('recipient_number', $message->sender_number)
            ->where('raw_body', 'like', '%Pesan di%Dihapus%')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($recentDm) {
            Log::info("ProcessMessageJob: skipping deletion DM to {$message->sender_number} — already sent recently");
            return;
        }

        $contact = \App\Models\Contact::where('phone_number', $message->sender_number)->first();
        $name = $contact?->name ?? $message->sender_name ?? 'Kak';
        $group = $message->group;
        $groupName = $group?->group_name ?? 'grup';

        $text = "🗑️ *Pesan di \"{$groupName}\" Dihapus*\n\n"
            . "Halo *{$name}*! 👋\n\n"
            . "Pesan yang kamu kirim ke grup *{$groupName}* tadi dihapus oleh sistem karena "
            . "grup ini *khusus untuk iklan jual-beli* produk halal. 🛍️\n\n"
            . "━━━━━━━━━━━━━━━━━\n"
            . "✅ *Boleh diposting di grup:*\n"
            . "• Iklan produk/jasa (foto + harga + deskripsi)\n\n"
            . "📌 *Ingin kirim konten selain iklan?*\n"
            . "Cukup awali pesanmu dengan label berikut:\n\n"
            . "  `@education` — tips, edukasi, artikel\n"
            . "  `@info` — informasi umum\n"
            . "  `@pengumuman` — pengumuman penting\n"
            . "  `@diskusi` — pertanyaan / diskusi\n"
            . "  `@berita` — berita terkini\n\n"
            . "_Contoh: `@diskusi Adakah yang jual kurma Madinah?`_\n\n"
            . "━━━━━━━━━━━━━━━━━\n"
            . 'Terima kasih sudah bergabung! 🙏';

        try {
            $wa->sendMessage($message->sender_number, $text);
        } catch (\Exception $e) {
            Log::warning('ProcessMessageJob: deleteNonAdGroupMessage DM failed', ['error' => $e->getMessage()]);
        }

        Log::info("ProcessMessageJob: deleted non-ad group message {$message->id} from {$message->sender_number}");
    }

    /**
     * Check if the message should be auto-deleted (e.g. contains "Delete Me").
     */
    private function shouldAutoDelete(Message $message): bool
    {
        // Only works for group messages (not DMs)
        if (is_null($message->whatsapp_group_id)) {
            return false;
        }

        $body = trim($message->raw_body ?? '');
        return stripos($body, 'delete me') !== false;
    }

    /**
     * Immediately delete the message from the WhatsApp group via the Baileys gateway.
     */
    private function executeAutoDelete(Message $message): void
    {
        $wa = app(WhacenterService::class);
        $payload = $message->raw_payload;

        // The Baileys gateway sends `_key` with the full message key for deletion
        $messageKey = $payload['_key'] ?? null;

        if ($messageKey) {
            $result = $wa->deleteMessage($messageKey);
        } else {
            // Fallback: reconstruct from stored fields
            $groupJid = $payload['group_id'] ?? $payload['from_group'] ?? null;
            $msgId = $message->message_id;
            $participant = $message->sender_number;

            if (!$groupJid || !$msgId) {
                Log::warning('ProcessMessageJob: Cannot auto-delete — missing group_id or message_id');
                return;
            }

            $result = $wa->deleteGroupMessage($groupJid, $msgId, $participant);
        }

        Log::info("ProcessMessageJob: Auto-delete result for message {$this->messageId}", $result);

        $message->update([
            'is_processed' => true,
            'processed_at' => now(),
            'message_category' => 'auto_deleted',
        ]);
    }

    /**
     * For a text-based ad listing, look for a companion image/video message sent by
     * the same sender in the same group within ±15 minutes. If found, attach that media
     * URL to the listing and run image analysis.
     */
    private function mergeCompanionMedia(Message $message, \App\Models\Listing $listing, ImageAnalyzerAgent $imageAnalyzer): void
    {
        $timestamp = $message->sent_at ?? $message->created_at;
        $from = $timestamp->copy()->subMinutes(60);
        $to = $timestamp->copy()->addMinutes(60);

        $companion = Message::where('whatsapp_group_id', $message->whatsapp_group_id)
            ->where('sender_number', $message->sender_number)
            ->where('id', '!=', $message->id)
            ->whereNotNull('media_url')
            ->whereIn('message_type', ['image', 'video'])
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('sent_at')->whereBetween('sent_at', [$from, $to]);
                })->orWhereBetween('created_at', [$from, $to]);
            })
            ->latest('sent_at')
            ->first();

        if (!$companion) {
            return;
        }

        // Attach companion image URL to listing
        if ($companion->media_url) {
            $urls = $listing->media_urls ?? [];
            if (!in_array($companion->media_url, $urls)) {
                $urls[] = $companion->media_url;
                $listing->update(['media_urls' => $urls]);
            }
        }

        // Mark companion as part of this ad
        $companion->update([
            'is_ad' => true,
            'ad_confidence' => $message->ad_confidence ?? 0.9,
            'is_processed' => true,
            'processed_at' => now(),
        ]);

        // If the companion image already has its own distinct listing, it is a separate
        // product (e.g. seller posted two different items in quick succession).
        // Preserve both listings — do NOT merge or delete either.
        $companionListing = $companion->listing;
        if ($companionListing && $companionListing->id !== $listing->id) {
            Log::info("ProcessMessageJob: companion image {$companion->id} already has listing {$companionListing->id} — skipping merge (separate product)");
            return;
        }

        // Run image analysis on the companion image and enrich the listing
        $imageAnalyzer->handle($companion, $listing);

        Log::info("ProcessMessageJob: merged companion image message {$companion->id} into listing {$listing->id}");
    }

    /**
     * Before creating a brand-new listing from a text-ad, check if the same sender
     * already has a recent listing in the same group (e.g. created from an image-only
     * message moments earlier). If found, enrich that listing with this text instead
     * of creating a duplicate.
     */
    private function tryMergeIntoExistingListing(Message $message, DataExtractorAgent $extractor): ?\App\Models\Listing
    {
        if (!$message->whatsapp_group_id || !$message->sender_number) {
            return null;
        }

        $timestamp = $message->sent_at ?? $message->created_at;
        $from = $timestamp->copy()->subMinutes(60);
        $to = $timestamp->copy()->addMinutes(60);

        // Find a recent listing from the same sender in the same group
        $existing = \App\Models\Listing::where('whatsapp_group_id', $message->whatsapp_group_id)
            ->where('contact_id', function ($q) use ($message) {
                $q
                    ->select('id')
                    ->from('contacts')
                    ->where('phone_number', $message->sender_number)
                    ->limit(1);
            })
            ->where('status', 'active')
            ->where('source_date', '>=', $from)
            ->where('source_date', '<=', $to)
            ->where('message_id', '!=', $message->id)
            ->latest('source_date')
            ->first();

        if (!$existing) {
            return null;
        }

        $text = trim($message->raw_body ?? '');
        $updates = [];

        // Enrich description — append the text content
        $desc = $existing->description ?? '';
        if ($text && (!$desc || !str_contains($desc, $text))) {
            $updates['description'] = $desc ? $desc . "\n\n" . $text : $text;
        }

        // Enrich title if current one is generic / image-derived
        if ($text && strlen($existing->title) < 30) {
            $updates['title'] = \Illuminate\Support\Str::limit($text, 90);
        }

        // Enrich price if not already set
        if (!$existing->price && !$existing->price_label) {
            $parsed = $this->parsePriceText($text);
            if ($parsed !== null) {
                if (is_string($parsed)) {
                    $updates['price_label'] = $parsed;
                } else {
                    $updates['price'] = $parsed;
                }
            }
        }

        if ($updates) {
            $existing->update($updates);
        }

        Log::info("ProcessMessageJob: merged text-ad message {$message->id} into existing listing {$existing->id} (image-first scenario)");
        return $existing;
    }

    /**
     * For an image-only message that was not classified as an ad, check if there is
     * a nearby text ad from the same sender (within ±5 minutes) that already has
     * a listing. If so, attach this image to that listing.
     * Returns true if a companion listing was found and image attached.
     */
    private function tryAttachImageToCompanionListing(Message $message, ImageAnalyzerAgent $imageAnalyzer): bool
    {
        $timestamp = $message->sent_at ?? $message->created_at;
        $from = $timestamp->copy()->subMinutes(60);
        $to = $timestamp->copy()->addMinutes(60);

        $candidates = Message::where('whatsapp_group_id', $message->whatsapp_group_id)
            ->where('sender_number', $message->sender_number)
            ->where('id', '!=', $message->id)
            ->where('is_ad', true)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('sent_at')->whereBetween('sent_at', [$from, $to]);
                })->orWhereBetween('created_at', [$from, $to]);
            })
            ->latest('sent_at')
            ->get();

        // Pick the first companion that has an actual listing
        $companion = null;
        $listing = null;
        foreach ($candidates as $candidate) {
            $candidateListing = $candidate->listing;
            if ($candidateListing) {
                $companion = $candidate;
                $listing = $candidateListing;
                break;
            }
        }

        if (!$companion || !$listing) {
            return false;
        }

        // Before attaching, check if this image has its OWN embedded price.
        // If it does, it's a separate product — let it flow to tryCreateListingFromImageOnly.
        if ($message->media_url) {
            $imageData = $imageAnalyzer->detectAdFromImage($message);
            if ($imageData && ($imageData['is_ad'] ?? false) && !empty($imageData['price'])) {
                Log::info("ProcessMessageJob: image message {$message->id} has own price ({$imageData['price']}), skipping companion attach → creating separate listing");
                return false;
            }
        }

        // Attach this image to the companion listing (only if we have a non-null URL)
        if ($message->media_url) {
            $urls = array_values(array_filter($listing->media_urls ?? []));
            if (!in_array($message->media_url, $urls)) {
                $urls[] = $message->media_url;
                $listing->update(['media_urls' => $urls]);
            }
            // Run image analysis and enrich the listing
            $imageAnalyzer->handle($message, $listing);
        }

        // Reclassify this image message as part of the same ad
        $message->update([
            'is_ad' => true,
            'ad_confidence' => $companion->ad_confidence ?? 0.9,
            'is_processed' => true,
            'processed_at' => now(),
        ]);

        Log::info("ProcessMessageJob: attached image message {$message->id} to companion listing {$listing->id}");
        return true;
    }

    /**
     * Image-only message with no companion text ad.
     * Ask Gemini vision to analyze the image and detect if it's an ad.
     * Replies in group when confirmed as listing, or sends DM when uncertain.
     * Returns the created Listing, or null.
     */
    private function tryCreateListingFromImageOnly(
        Message $message,
        ImageAnalyzerAgent $imageAnalyzer,
        DataExtractorAgent $extractor
    ): ?\App\Models\Listing {
        // Video: we can't analyze frames — ask sender for context via DM then stop
        if ($message->message_type === 'video') {
            $this->sendImageClarificationDm($message, 'video');
            $message->update(['is_processed' => true, 'processed_at' => now()]);
            return null;
        }

        // Only analyze image/sticker types with Gemini Vision
        if (!in_array($message->message_type, ['image', 'sticker'])) {
            $message->update(['is_processed' => true, 'processed_at' => now()]);
            return null;
        }

        $adData = $imageAnalyzer->detectAdFromImage($message);

        // Technical failure (Gemini down, network error, bad JSON)
        if (!$adData) {
            $message->update(['is_processed' => true, 'processed_at' => now()]);
            return null;
        }

        if (empty($adData['is_ad'])) {
            // Not an ad
            $message->update(['is_processed' => true, 'processed_at' => now(), 'is_ad' => false, 'ad_confidence' => $adData['confidence'] ?? 0.0]);
            // AI saw something but wasn't sure — ask sender via DM
            if (!empty($adData['needs_clarification'])) {
                $this->sendImageClarificationDm($message, 'image');
            }
            return null;
        }

        // It's an ad! Mark the message accordingly
        $message->update([
            'is_ad' => true,
            'ad_confidence' => $adData['confidence'] ?? 0.85,
            'is_processed' => true,
            'processed_at' => now(),
        ]);

        // Resolve or create contact from sender
        $contact = \App\Models\Contact::where('phone_number', $message->sender_number)->first();
        if ($contact) {
            $contact->increment('ad_count');
        }

        // Resolve category
        $categoryId = null;
        if (!empty($adData['category'])) {
            $category = \App\Models\Category::where('name', $adData['category'])
                ->orWhere('slug', \Illuminate\Support\Str::slug($adData['category']))
                ->first();
            if ($category) {
                $categoryId = $category->id;
                $category->increment('listing_count');
            }
        }

        // Resolve contact from extracted phone if present
        $contactId = $contact?->id;
        if (!empty($adData['contact_number'])) {
            $extracted = \App\Models\Contact::firstOrCreate(
                ['phone_number' => $adData['contact_number']],
                ['name' => null]
            );
            $extracted->increment('ad_count');
            $contactId = $extracted->id;
        }

        // Build title from visible text or description
        $title = $adData['title']
            ?? \Illuminate\Support\Str::limit($adData['visible_text'] ?? $adData['description'] ?? 'Produk dari gambar', 90);

        // Resolve contact_number: prefer AI-extracted, then sender, then contact's phone
        $contactNumber = $adData['contact_number']
            ?? (preg_match('/^62\d{8,13}$/', $message->sender_number ?? '') ? $message->sender_number : null);
        if (!$contactNumber && $contactId) {
            $fallbackPhone = \App\Models\Contact::find($contactId)?->phone_number;
            if ($fallbackPhone && preg_match('/^62\d{8,13}$/', $fallbackPhone)) {
                $contactNumber = $fallbackPhone;
            }
        }

        $listing = \App\Models\Listing::updateOrCreate(
            ['message_id' => $message->id],
            [
                'whatsapp_group_id' => $message->whatsapp_group_id,
                'category_id' => $categoryId,
                'contact_id' => $contactId,
                'title' => $title,
                'description' => $adData['description'] ?? $adData['visible_text'] ?? null,
                'price' => $adData['price'] ?? null,
                'price_label' => $adData['price_label'] ?? null,
                'contact_number' => $contactNumber,
                'location' => $adData['location'] ?? null,
                'condition' => $adData['condition'] ?? 'unknown',
                'media_urls' => $message->media_url ? [$message->media_url] : [],
                'status' => 'active',
                'source_date' => $message->sent_at ?? $message->created_at,
            ]
        );

        Log::info("ProcessMessageJob: created listing {$listing->id} from image-only message {$message->id}", [
            'title' => $listing->title,
            'confidence' => $adData['confidence'],
        ]);

        return $listing;
    }

    /**
     * For a text-only message that isn't classified as an ad on its own, check if
     * the same sender has a recent listing in the same group (within ±10 min).
     * If found, append the text and any parsed price to that listing.
     * Returns the companion listing if merged, otherwise null.
     */
    private function tryMergeTextIntoCompanionListing(Message $message): ?\App\Models\Listing
    {
        $text = trim($message->raw_body ?? '');
        if (!$text || !$message->whatsapp_group_id) {
            return null;
        }

        $timestamp = $message->sent_at ?? $message->created_at;
        $from = $timestamp->copy()->subMinutes(10);
        $to = $timestamp->copy()->addMinutes(10);

        // Get all candidate companions (is_ad=true within window), then pick the one with a listing
        $candidates = Message::where('whatsapp_group_id', $message->whatsapp_group_id)
            ->where('sender_number', $message->sender_number)
            ->where('id', '!=', $message->id)
            ->where('is_ad', true)
            ->where(function ($q) use ($from, $to) {
                $q->where(function ($inner) use ($from, $to) {
                    $inner->whereNotNull('sent_at')->whereBetween('sent_at', [$from, $to]);
                })->orWhereBetween('created_at', [$from, $to]);
            })
            ->latest('sent_at')
            ->get();

        // Prefer the companion that has an actual listing
        $companionMessage = null;
        $listing = null;
        foreach ($candidates as $candidate) {
            $candidateListing = $candidate->listing;
            if ($candidateListing) {
                $companionMessage = $candidate;
                $listing = $candidateListing;
                break;
            }
        }

        if (!$companionMessage || !$listing) {
            return null;
        }

        $updates = [];

        // Append text to description for richer context
        $existingDesc = $listing->description ?? '';
        $updates['description'] = $existingDesc ? $existingDesc . "\n" . $text : $text;

        // Enrich price if not already set
        if (!$listing->price && !$listing->price_label) {
            $parsed = $this->parsePriceText($text);
            if ($parsed !== null) {
                if (is_string($parsed)) {
                    $updates['price_label'] = $parsed;
                } else {
                    $updates['price'] = $parsed;
                }
            }
        }

        $listing->update($updates);

        $message->update([
            'is_ad' => true,
            'ad_confidence' => $companionMessage->ad_confidence ?? 0.85,
            'is_processed' => true,
            'processed_at' => now(),
            'message_category' => 'ad_continuation',
        ]);

        Log::info("ProcessMessageJob: merged follow-up text message {$message->id} into listing {$listing->id}");
        return $listing;
    }

    /**
     * Parse a price string like "4.4 miliyar", "1.5 juta", "Rp 150.000".
     * Returns float for parsed numeric prices, string label for unparseable price text,
     * or null if no price detected.
     */
    private function parsePriceText(string $text): float|string|null
    {
        // Normalize comma used as decimal separator
        $t = str_replace(',', '.', $text);

        if (preg_match('/(\d+(?:\.\d+)?)\s*(miliar|miliyar|milyar)/i', $t, $m)) {
            return (float) $m[1] * 1_000_000_000;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*juta/i', $t, $m)) {
            return (float) $m[1] * 1_000_000;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(ribu|rb|k)\b/i', $t, $m)) {
            return (float) $m[1] * 1_000;
        }
        if (preg_match('/Rp\.?\s*([\d.]+)/i', $t, $m)) {
            $num = str_replace('.', '', $m[1]);
            if (is_numeric($num)) {
                return (float) $num;
            }
        }
        // If text contains price keywords but wasn't parseable, store as label
        if (preg_match('/harga|price|rp|nego|fixed/i', $t)) {
            return $text;
        }
        return null;
    }

    /**
     * Send a private (DM) WhatsApp message asking the sender to clarify
     * whether their uploaded image/video is a marketplace ad.
     */
    private function sendImageClarificationDm(Message $message, string $mediaType = 'image'): void
    {
        if (!$message->sender_number) {
            return;
        }

        $message->loadMissing('group');
        $senderName = $message->sender_name ?? 'Kak';
        $groupName = $message->group?->group_name ?? 'grup';

        $mediaLabel = ($mediaType === 'video') ? 'video' : 'foto';

        $text = "Halo *{$senderName}*! 😊\n\n"
            . "Makasih ya udah kirim {$mediaLabel} di grup *{$groupName}*. "
            . "Tapi kami belum bisa pastiin itu iklan jualan atau bukan.\n\n"
            . "Kalau emang mau jualan, bisa tambahin keterangan kayak:\n"
            . "- Nama barang/jasanya apa\n"
            . "- Harganya berapa\n"
            . "- Lokasi kamu di mana\n\n"
            . "Tinggal kirim ulang {$mediaLabel}nya + keterangan dalam satu pesan ya.\n\n"
            . "Kalau bukan iklan, abaikan aja pesan ini 🙏";

        app(WhacenterService::class)->sendMessage($message->sender_number, $text);

        Log::info("ProcessMessageJob: sent clarification DM to {$message->sender_number} for message {$message->id}");
    }

    /**
     * Pesan iklan terdeteksi AI tapi extractor gagal membuat listing.
     * Kirim DM ke penjual minta format ulang.
     */
    private function sendAdExtractionFailedDm(Message $message): void
    {
        if (!$message->sender_number) {
            return;
        }
        $wa = app(WhacenterService::class);
        $contact = \App\Models\Contact::where('phone_number', $message->sender_number)->first();
        $name = $contact?->name ?? $message->sender_name ?? 'Kak';
        $groupName = $message->group?->group_name ?? 'grup';

        // 0. Backup media URL before deletion — retain for recovery
        if ($message->media_url) {
            Log::warning("ProcessMessageJob: BACKUP before delete — message {$message->id} media_url={$message->media_url} sender={$message->sender_number}");
        }

        // 1. Delete the message from the group — it couldn't be processed into a listing
        try {
            $payload = $message->raw_payload ?? [];
            $messageKey = $payload['_key'] ?? null;
            if ($messageKey) {
                $wa->deleteMessage($messageKey);
            } elseif ($message->message_id) {
                $groupJid = $payload['group_id'] ?? $payload['from_group'] ?? null;
                if ($groupJid) {
                    $wa->deleteGroupMessage($groupJid, $message->message_id, $message->sender_number);
                }
            }
        } catch (\Exception $e) {
            Log::warning('ProcessMessageJob: sendAdExtractionFailedDm delete failed', ['error' => $e->getMessage()]);
        }

        $message->update(['message_category' => 'extraction_failed']);

        // 2. DM the sender to repost with proper format
        $text = "Halo *{$name}*! 🙏\n\n"
            . "Eh tadi pesan kamu di *{$groupName}* kelihatannya kurang info jualannya, jadi aku hapus dulu 😊\n\n"
            . "Kalau emang mau jualan, coba kirim ulang ya — paling penting ada nama barangnya, harga, sama cara hubungi kamu.\n\n"
            . "Contoh simpel: _\"Jual Baju Koko Rp150rb, ukuran L, WA 0858xxxx\"_ 😊";

        try {
            $wa->sendMessage($message->sender_number, $text);
            Log::info("ProcessMessageJob: sent ad-extraction-failed DM to {$message->sender_number}");
        } catch (\Exception $e) {
            Log::warning('ProcessMessageJob: sendAdExtractionFailedDm sendDM failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Kirim konfirmasi singkat ke grup bahwa konten berlabel diterima.
     */
    private function acknowledgeLabel(Message $message, string $labeledCategory): void
    {
        $group = $message->group;
        if (!$group || !$message->sender_number) {
            return;
        }
        $name = $message->sender_name ?? $message->sender_number;
        $icon = match ($labeledCategory) {
            'education' => '📚',
            'info' => 'ℹ️',
            'announcement' => '📢',
            'discussion' => '💬',
            'news' => '📰',
            default => '✅',
        };
        $label = match ($labeledCategory) {
            'education' => 'edukasi',
            'info' => 'informasi',
            'announcement' => 'pengumuman',
            'discussion' => 'diskusi',
            'news' => 'berita',
            default => $labeledCategory,
        };
        try {
            app(WhacenterService::class)->sendGroupMessage(
                $group->group_name,
                "{$icon} *Konten {$label} dari {$name} diterima dan disimpan.* Terima kasih telah berbagi! 🙏"
            );
        } catch (\Exception $e) {
            Log::warning('ProcessMessageJob: acknowledgeLabel failed', ['error' => $e->getMessage()]);
        }
    }
}
