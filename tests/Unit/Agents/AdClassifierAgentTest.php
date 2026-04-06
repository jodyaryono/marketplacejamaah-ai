<?php

namespace Tests\Unit\Agents;

use App\Agents\AdClassifierAgent;
use App\Models\AgentLog;
use App\Models\Message;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdClassifierAgentTest extends TestCase
{
    use RefreshDatabase;

    private function makeMessage(string $body, string $type = 'text'): Message
    {
        return Message::factory()->create([
            'raw_body'     => $body,
            'message_type' => $type,
            'direction'    => 'in',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_very_short_messages_without_calling_gemini(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldNotReceive('generateJson');

        $agent   = new AdClassifierAgent($gemini);
        $message = $this->makeMessage('ok');

        $result = $agent->handle($message, ['text_content' => 'ok', 'word_count' => 1]);

        $this->assertFalse($result['is_ad']);
        $this->assertEquals('one_liner_guard', $result['reason']);
        $this->assertEquals(0.0, $result['confidence']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_classifies_as_ad_when_gemini_returns_true(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn([
            'is_ad'      => true,
            'confidence' => 0.92,
            'reason'     => 'explicit_price_and_product',
        ]);

        $agent   = new AdClassifierAgent($gemini);
        $message = $this->makeMessage('Jual gamis ukuran L harga 150rb kondisi baru COD Bekasi');

        $result = $agent->handle($message, ['text_content' => $message->raw_body, 'word_count' => 10]);

        $this->assertTrue($result['is_ad']);
        $this->assertEquals(0.92, $result['confidence']);
        $message->refresh();
        $this->assertTrue($message->is_ad);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_heuristic_fallback_when_gemini_fails(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn(null);

        $agent   = new AdClassifierAgent($gemini);
        $message = $this->makeMessage('jual kurma ajwa harga nego ready stock');

        $result = $agent->handle($message, [
            'text_content' => $message->raw_body,
            'word_count'   => 6,
            'has_price'    => false,
        ]);

        // "jual" and "harga" and "ready stock" are heuristic keywords → should be true
        $this->assertTrue($result['is_ad']);
        $this->assertEquals('heuristic_fallback', $result['reason']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_classifies_non_ad_conversation_correctly(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn([
            'is_ad'      => false,
            'confidence' => 0.05,
            'reason'     => 'general_question',
        ]);

        $agent   = new AdClassifierAgent($gemini);
        $message = $this->makeMessage('Assalamualaikum, kapan grup ini aktif berjualan lagi?');

        $result = $agent->handle($message, ['text_content' => $message->raw_body, 'word_count' => 8]);

        $this->assertFalse($result['is_ad']);
        $message->refresh();
        $this->assertFalse($message->is_ad);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_logs_agent_run_to_database(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->andReturn(['is_ad' => false, 'confidence' => 0.1, 'reason' => 'test']);

        $agent   = new AdClassifierAgent($gemini);
        $message = $this->makeMessage('Apakah masih ada barang dagangan tersisa untuk hari ini?');

        $agent->handle($message, ['text_content' => $message->raw_body, 'word_count' => 9]);

        $this->assertDatabaseHas('agent_logs', [
            'agent_name' => 'AdClassifierAgent',
            'message_id' => $message->id,
            'status'     => 'success',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
