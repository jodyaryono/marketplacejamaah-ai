<?php

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceCircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset circuit-breaker state before each test
        Cache::forget('gemini_cb_failures');
        Cache::forget('gemini_cb_open_until');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function circuit_is_closed_when_no_failures_recorded(): void
    {
        $service = app(GeminiService::class);
        $this->assertFalse($service->isCircuitOpen());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function circuit_opens_after_threshold_consecutive_failures(): void
    {
        // Simulate 5 consecutive HTTP 500 failures
        Http::fake(fn() => Http::response(['error' => 'internal'], 500));

        $service = app(GeminiService::class);

        for ($i = 0; $i < 5; $i++) {
            $service->generateContent('test prompt');
        }

        $this->assertTrue($service->isCircuitOpen());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function when_circuit_is_open_generate_content_returns_null_immediately(): void
    {
        // Manually open the circuit
        Cache::put('gemini_cb_open_until', now()->addMinutes(2)->timestamp, now()->addMinutes(3));

        // HTTP should never be called
        Http::fake(fn() => $this->fail('HTTP was called while circuit was open'));

        $service = app(GeminiService::class);
        $result  = $service->generateContent('should be skipped');

        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function circuit_resets_after_successful_call(): void
    {
        // Pre-load 3 failures (below threshold)
        Cache::put('gemini_cb_failures', 3, now()->addMinutes(10));

        Http::fake([
            '*' => Http::response([
                'candidates'    => [['content' => ['parts' => [['text' => 'PONG']]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 2],
            ], 200),
        ]);

        $service = app(GeminiService::class);
        $result  = $service->generateContent('Reply with PONG');

        $this->assertSame('PONG', $result);
        // Failures counter should be cleared after success
        $this->assertNull(Cache::get('gemini_cb_failures'));
        $this->assertFalse($service->isCircuitOpen());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function generate_content_returns_cached_value_without_http_call(): void
    {
        $cacheKey = 'gemini_resp_' . md5('cached prompt');
        Cache::put($cacheKey, 'cached result', now()->addMinutes(5));

        Http::fake(fn() => $this->fail('HTTP was called despite cache hit'));

        $service = app(GeminiService::class);
        $result  = $service->generateContent('cached prompt', cacheTtl: 5);

        $this->assertSame('cached result', $result);
    }
}
