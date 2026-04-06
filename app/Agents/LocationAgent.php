<?php

namespace App\Agents;

use App\Models\Contact;
use App\Models\Setting;
use App\Services\GeminiService;

class LocationAgent
{
    private string $baseUrl;

    public function __construct(
        private GeminiService $gemini,
        private SearchAgent $search,
    ) {
        $this->baseUrl = rtrim(config('app.url'), '/');
    }

    /**
     * Search listings near a shared GPS location.
     */
    public function handleLocationSearch(float $lat, float $lng, string $senderPhone): string
    {
        $areaName = $this->reverseGeocode($lat, $lng);
        $userQuery = 'produk atau penjual di sekitar ' . ($areaName ?: "koordinat {$lat},{$lng}");
        $listings  = $this->search->ragRetrieveListings($userQuery, null, 100);
        $areaLabel = $areaName ? "*{$areaName}*" : 'lokasi yang kamu bagikan';

        if ($listings->isEmpty()) {
            return "📍 Terima kasih sudah berbagi lokasi di {$areaLabel}.\n\n"
                . "😔 Belum ada iklan yang cocok dengan lokasimu saat ini.\n\n"
                . '_Coba ketik nama produk yang kamu cari, misalnya: "ada gamis ukuran L?"_';
        }

        $lines = ["📍 *Produk Sekitar {$areaLabel}*\n"];
        foreach ($listings->take(5) as $i => $listing) {
            $link    = "{$this->baseUrl}/p/{$listing->id}";
            $price   = $listing->price_label
                ?? ($listing->price > 0 ? 'Rp ' . number_format($listing->price, 0, ',', '.') : 'Harga nego');
            $loc     = $listing->location ? " 📍 {$listing->location}" : '';
            $seller  = $listing->contact?->name ?? 'Penjual';
            $lines[] = ($i + 1) . ". *{$listing->title}*\n"
                . "   💰 {$price}{$loc}\n"
                . "   👤 {$seller}\n"
                . "   🔗 {$link}";
        }
        $lines[] = "\n_Ketik nama produk untuk pencarian lebih spesifik._";

        return implode("\n", $lines);
    }

    /**
     * Save GPS coordinates and reverse-geocoded address to the contact record.
     */
    public function handleUpdateBusinessLocation(float $lat, float $lng, string $senderPhone): string
    {
        $areaName = $this->reverseGeocode($lat, $lng);
        $address  = $areaName ?: "lat:{$lat}, lng:{$lng}";

        $updated = Contact::where('phone_number', $senderPhone)
            ->update(['latitude' => $lat, 'longitude' => $lng, 'address' => $address]);

        if ($updated) {
            return "✅ *Lokasi bisnis berhasil diperbarui!*\n\n"
                . "📍 Lokasi kamu sekarang tercatat di *{$address}*\n\n"
                . '_Lokasi ini akan ditampilkan di profil penjualmu._';
        }

        return '❌ Gagal memperbarui lokasi. Pastikan kamu sudah terdaftar sebagai member.';
    }

    /**
     * Reverse-geocode coordinates using Gemini (cached 30 days per unique lat/lng).
     */
    private function reverseGeocode(float $lat, float $lng): string
    {
        $geoTemplate = Setting::get('prompt_bot_reverse_geocode', 'Koordinat GPS: latitude {lat}, longitude {lng}. Area apa di Indonesia?');
        $prompt      = str_replace(['{lat}', '{lng}'], [round($lat, 4), round($lng, 4)], $geoTemplate);

        // Cache by rounded coords — area names rarely change
        return trim($this->gemini->generateContent($prompt, cacheTtl: 60 * 24 * 30) ?? '');
    }
}
