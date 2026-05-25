<?php

namespace App\Jobs;

use App\Agents\AdBuilderAgent;
use App\Services\WhacenterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAdBuilderBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // jangan retry — Gemini sudah punya internal fallback Groq
    public int $timeout = 120; // multi-image Gemini call butuh waktu lebih

    public function __construct(
        private string $phone
    ) {}

    public function handle(AdBuilderAgent $agent): void
    {
        $agent->executeBatchAnalysis($this->phone);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('ProcessAdBuilderBatchJob failed', [
            'phone' => $this->phone,
            'error' => $exception->getMessage(),
        ]);
        try {
            app(WhacenterService::class)->sendMessage(
                $this->phone,
                "❌ AI sedang sibuk, draft iklan gagal dibuat. Coba ketik *cukup* lagi sebentar lagi, atau *batal* untuk membatalkan."
            );
        } catch (\Throwable $e) {
            Log::warning('ProcessAdBuilderBatchJob failed notify error', ['error' => $e->getMessage()]);
        }
    }
}
