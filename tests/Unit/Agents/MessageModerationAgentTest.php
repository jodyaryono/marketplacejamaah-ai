<?php

namespace Tests\Unit\Agents;

use App\Agents\MessageModerationAgent;
use App\Models\Contact;
use App\Models\Message;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MessageModerationAgentTest extends TestCase
{
    use RefreshDatabase;

    private function makeMessage(string $body, bool $isAd = false): Message
    {
        return Message::factory()->create([
            'raw_body'     => $body,
            'is_ad'        => $isAd,
            'direction'    => 'in',
            'message_type' => 'text',
        ]);
    }

    private function makeContact(Message $message): Contact
    {
        return Contact::factory()->create([
            'phone_number'  => $message->sender_number,
            'warning_count' => 0,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function legitimate_ad_is_skipped_without_gemini_call(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldNotReceive('generateJson');

        $agent   = new MessageModerationAgent($gemini);
        $message = $this->makeMessage('Jual hijab premium ukuran M harga 75rb', isAd: true);

        $result = $agent->handle($message);

        $this->assertFalse($result['is_violation']);
        $this->assertEquals('ad', $result['category']);
        $message->refresh();
        $this->assertFalse($message->violation_detected);

        // Agent log should be "skipped"
        $this->assertDatabaseHas('agent_logs', [
            'agent_name' => 'MessageModerationAgent',
            'message_id' => $message->id,
            'status'     => 'skipped',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function scam_ad_phrases_trigger_full_gemini_moderation(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn([
            'category'          => 'spam',
            'is_violation'      => true,
            'violation_severity'=> 'high',
            'violation_reason'  => 'MLM/investasi bodong',
            'reply_group_text'  => null,
            'reply_dm_text'     => 'Pesan terindikasi scam',
            'language_tone'     => 'formal',
        ]);

        $agent   = new MessageModerationAgent($gemini);
        $message = $this->makeMessage('Join sekarang passive income tanpa modal juta per hari', isAd: true);

        $result = $agent->handle($message);

        $this->assertTrue($result['is_violation']);
        $this->assertEquals('spam', $result['category']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function insult_message_detected_by_fallback_regex_when_gemini_fails(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn(null);

        $agent   = new MessageModerationAgent($gemini);
        $message = $this->makeMessage('Anjing dasar goblok');
        $contact = $this->makeContact($message);

        $result = $agent->handle($message);

        $this->assertTrue($result['is_violation']);
        $this->assertEquals('insult', $result['category']);

        // Warning count should be incremented
        $contact->refresh();
        $this->assertEquals(1, $contact->warning_count);
        $this->assertEquals(1, $contact->total_violations);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function spam_message_detected_by_fallback_regex_when_gemini_fails(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn(null);

        $agent   = new MessageModerationAgent($gemini);
        $message = $this->makeMessage('Daftar sekarang klik link bit.ly/xxxx profit harian 3 juta per hari');

        $result = $agent->handle($message);

        $this->assertTrue($result['is_violation']);
        $this->assertEquals('spam', $result['category']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function clean_message_returns_no_violation(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn([
            'category'           => 'general',
            'is_violation'       => false,
            'violation_severity' => null,
            'violation_reason'   => null,
            'reply_group_text'   => null,
            'reply_dm_text'      => null,
            'language_tone'      => 'informal',
        ]);

        $agent   = new MessageModerationAgent($gemini);
        $message = $this->makeMessage('Mau tanya, ada yang jual kurma tidak?');

        $result = $agent->handle($message);

        $this->assertFalse($result['is_violation']);
        $message->refresh();
        $this->assertFalse($message->violation_detected);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function violation_increments_contact_warning_count(): void
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateJson')->once()->andReturn([
            'category'           => 'insult',
            'is_violation'       => true,
            'violation_severity' => 'high',
            'violation_reason'   => 'kata kasar',
            'reply_group_text'   => null,
            'reply_dm_text'      => 'Peringatan',
            'language_tone'      => 'formal',
        ]);

        $agent   = new MessageModerationAgent($gemini);
        $message = $this->makeMessage('Kata kasar berat');
        $contact = $this->makeContact($message);

        $agent->handle($message);

        $contact->refresh();
        $this->assertEquals(1, $contact->warning_count);
        $this->assertEquals(1, $contact->total_violations);
        $this->assertNotNull($contact->last_warning_at);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
