<?php

namespace App\Agents;

use App\Models\Setting;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KtpScanAgent
{
    public function __construct(
        private GeminiService $gemini,
    ) {}

    /**
     * Prompt user to send their KTP photo and store intent in cache.
     */
    public function requestScan(string $phoneNumber): string
    {
        Cache::put('ktp_pending:' . $phoneNumber, true, now()->addMinutes(10));

        return "📋 *Scan KTP dengan AI*\n\n"
            . "Silakan kirim foto KTP kamu sekarang.\n\n"
            . "🤖 AI akan membaca dan mengekstrak:\n"
            . "• NIK\n"
            . "• Nama lengkap\n"
            . "• Tempat/tanggal lahir\n"
            . "• Alamat lengkap\n"
            . "• Agama, status perkawinan, pekerjaan\n\n"
            . "_Pastikan foto KTP jelas, tidak buram, dan seluruh kartu terlihat._\n"
            . '_Data hanya dibaca, tidak disimpan._';
    }

    /**
     * Download image from URL then analyze as KTP.
     */
    public function analyzeFromUrl(string $mediaUrl): string
    {
        try {
            $imageData = Http::timeout(15)->get($mediaUrl);
            if ($imageData->failed()) {
                return '❌ Gagal mengunduh gambar. Silakan coba kirim ulang foto KTP kamu.';
            }
            $base64   = base64_encode($imageData->body());
            $mimeType = $imageData->header('Content-Type') ?: 'image/jpeg';
            return $this->analyzeBase64($base64, $mimeType);
        } catch (\Exception $e) {
            Log::error('KtpScanAgent::analyzeFromUrl failed', ['error' => $e->getMessage()]);
            return '❌ Terjadi kesalahan saat membaca KTP. Silakan coba lagi.';
        }
    }

    /**
     * Analyze a base64-encoded KTP image using Gemini vision.
     */
    public function analyzeBase64(string $base64, string $mimeType): string
    {
        try {
            $prompt = Setting::get('prompt_bot_ktp_scan', 'Baca KTP Indonesia dari gambar. Jawab JSON.');
            $result = $this->gemini->analyzeImageWithText($base64, $mimeType, $prompt);

            if (!$result) {
                return '❌ Gagal membaca gambar. Silakan coba lagi dengan foto yang lebih jelas.';
            }

            $clean  = preg_replace('/```json\s*/i', '', $result);
            $clean  = preg_replace('/```\s*/i', '', $clean);
            $parsed = json_decode(trim($clean), true);

            if (!$parsed || !($parsed['is_ktp'] ?? false)) {
                return "📷 *Foto Bukan KTP*\n\n"
                    . "Gambar yang dikirim tidak terdeteksi sebagai KTP.\n\n"
                    . "Untuk scan KTP, ketik *scan ktp* lalu kirim foto KTP kamu.\n\n"
                    . "_Tips foto KTP yang baik:\n"
                    . "• Pencahayaan cukup, tidak gelap\n"
                    . "• Seluruh KTP terlihat, tidak terpotong\n"
                    . '• Foto tidak buram/blur_';
            }

            $lines = ["🪪 *Hasil Scan KTP*\n"];
            if (!empty($parsed['nik']))
                $lines[] = "📌 *NIK:* {$parsed['nik']}";
            if (!empty($parsed['nama']))
                $lines[] = "👤 *Nama:* {$parsed['nama']}";
            $ttl = trim(($parsed['tempat_lahir'] ?? '') . ', ' . ($parsed['tanggal_lahir'] ?? ''), ', ');
            if ($ttl !== ',' && $ttl !== '')
                $lines[] = "🎂 *Tgl Lahir:* {$ttl}";
            if (!empty($parsed['jenis_kelamin']))
                $lines[] = "⚧ *Jenis Kelamin:* {$parsed['jenis_kelamin']}";
            $alamatBagian = array_filter([
                $parsed['alamat'] ?? null,
                !empty($parsed['rt_rw']) ? 'RT/RW ' . $parsed['rt_rw'] : null,
                $parsed['kelurahan'] ?? null,
                !empty($parsed['kecamatan']) ? 'Kec. ' . $parsed['kecamatan'] : null,
                $parsed['kabupaten_kota'] ?? null,
                $parsed['provinsi'] ?? null,
            ]);
            if (!empty($alamatBagian))
                $lines[] = '🏠 *Alamat:* ' . implode(', ', $alamatBagian);
            if (!empty($parsed['agama']))
                $lines[] = "🕌 *Agama:* {$parsed['agama']}";
            if (!empty($parsed['status_perkawinan']))
                $lines[] = "💍 *Status:* {$parsed['status_perkawinan']}";
            if (!empty($parsed['pekerjaan']))
                $lines[] = "💼 *Pekerjaan:* {$parsed['pekerjaan']}";
            if (!empty($parsed['berlaku_hingga']))
                $lines[] = "📅 *Berlaku Hingga:* {$parsed['berlaku_hingga']}";
            $lines[] = "\n_⚠️ Data di atas hanya hasil pembacaan AI dan tidak disimpan._";
            $lines[] = '_Pastikan keakuratan dengan membandingkan langsung ke KTP fisik._';

            return implode("\n", $lines);
        } catch (\Exception $e) {
            Log::error('KtpScanAgent::analyzeBase64 failed', ['error' => $e->getMessage()]);
            return '❌ Terjadi kesalahan saat membaca KTP. Silakan coba lagi.';
        }
    }
}
