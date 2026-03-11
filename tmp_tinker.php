$l = App\Models\Listing::with('message')->first();
echo $l->title . "|" . json_encode($l->media_urls) . "|" . ($l->message?->media_url ?? 'no_msg_media');
