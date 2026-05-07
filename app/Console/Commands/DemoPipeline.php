<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMessageJob;
use App\Models\AgentLog;
use App\Models\Listing;
use App\Models\Message;
use App\Models\WhatsappGroup;
use Illuminate\Console\Command;

/**
 * Demo end-to-end multi-agent pipeline tanpa perlu WhatsApp gateway aktif.
 *
 * Membuat Message simulasi seperti yang masuk dari WAG, men-trigger pipeline
 * 16-agent secara synchronous, lalu mencetak reasoning trace dari `agent_logs`.
 *
 * Dipakai untuk:
 * - Submission QHomemart AI Agent Competition 2026 (reproducibility & demo)
 * - Smoke test setelah deploy
 * - Onboarding developer baru
 */
class DemoPipeline extends Command
{
    protected $signature = 'demo:pipeline
        {scenario? : ad|violation|image-only|search (default: ad)}
        {--cleanup : Hapus message & listing yang dibuat oleh demo setelah selesai}';

    protected $description = 'Demo end-to-end multi-agent pipeline + cetak reasoning trace';

    private array $scenarios = [
        'ad' => [
            'label' => '✅ Skenario IKLAN VALID',
            'sender_number' => '628111111001',
            'sender_name' => 'Demo Seller',
            'raw_body' => "Jual Kurma Ajwa Premium 1kg Rp280.000\nLokasi Bandung, fresh stock\nWA 0858-1234-5678",
            'expect' => 'is_ad=true, is_violation=false, listing created',
        ],
        'violation' => [
            'label' => '🚨 Skenario VIOLATION (scam/judol)',
            'sender_number' => '628111111002',
            'sender_name' => 'Demo Scammer',
            'raw_body' => "PELUANG EMAS! Robot trading binary, profit 5juta per hari tanpa kerja. WD mudah, downline auto cuan. DM sekarang!",
            'expect' => 'is_violation=true, no listing, warning issued',
        ],
        'image-only' => [
            'label' => '🖼️  Skenario FOTO-ONLY (vision agent)',
            'sender_number' => '628111111003',
            'sender_name' => 'Demo Photo',
            'raw_body' => '',
            'message_type' => 'image',
            'expect' => 'ImageAnalyzerAgent extract product info dari foto saja',
        ],
        'search' => [
            'label' => '🔎 Skenario SEARCH (DM ke bot)',
            'sender_number' => '628111111004',
            'sender_name' => 'Demo Buyer',
            'raw_body' => 'cari kurma',
            'in_dm' => true,
            'expect' => 'BotQueryAgent → SearchAgent route, return matching listings',
        ],
    ];

    public function handle(): int
    {
        $this->banner();

        $scenario = $this->argument('scenario') ?: 'ad';
        if (!isset($this->scenarios[$scenario])) {
            $this->error("Scenario tidak dikenal: {$scenario}");
            $this->line('Pilihan: ' . implode(', ', array_keys($this->scenarios)));
            return self::FAILURE;
        }

        $config = $this->scenarios[$scenario];
        $this->line("\n<fg=cyan>📋 {$config['label']}</>");
        $this->line("Expect: {$config['expect']}\n");

        $group = WhatsappGroup::where('is_active', true)->first();
        if (!$group && empty($config['in_dm'])) {
            $this->error('Tidak ada WhatsappGroup aktif. Jalankan: php artisan db:seed --class=DemoGroupSeeder');
            return self::FAILURE;
        }

        $message = Message::create([
            'sender_number' => $config['sender_number'],
            'sender_name' => $config['sender_name'],
            'raw_body' => $config['raw_body'],
            'message_type' => $config['message_type'] ?? 'text',
            'whatsapp_group_id' => empty($config['in_dm']) ? $group->id : null,
            'direction' => 'in',
            'message_id' => 'demo-' . uniqid(),
            'sent_at' => now(),
        ]);

        $this->line("📨 Created Message #{$message->id}: \"{$message->raw_body}\"");
        $this->line("⏳ Triggering pipeline (sync)...\n");

        $start = microtime(true);

        // Run synchronously biar trace langsung kelihatan
        ProcessMessageJob::dispatchSync($message->id);

        $elapsed = (int) ((microtime(true) - $start) * 1000);
        $message->refresh();

        $this->printAgentTrace($message);
        $this->printResult($message, $elapsed);

        if ($this->option('cleanup')) {
            $this->cleanup($message);
        }

        return self::SUCCESS;
    }

    private function banner(): void
    {
        $this->line('');
        $this->line('<fg=blue>╔════════════════════════════════════════════════════════════╗</>');
        $this->line('<fg=blue>║</>  <fg=white;options=bold>Marketplace Jamaah AI — Multi-Agent Demo Pipeline</>      <fg=blue>║</>');
        $this->line('<fg=blue>║</>  <fg=gray>16 agents · Reasoning + Collaboration + Real-time</>      <fg=blue>║</>');
        $this->line('<fg=blue>╚════════════════════════════════════════════════════════════╝</>');
    }

    private function printAgentTrace(Message $message): void
    {
        $this->line('<fg=yellow;options=bold>🤖 AGENT REASONING TRACE</>');
        $this->line(str_repeat('─', 70));

        $logs = AgentLog::where('message_id', $message->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($logs->isEmpty()) {
            $this->line('<fg=red>(no agent calls logged — periksa GEMINI_API_KEY & queue worker)</>');
            return;
        }

        $rows = [];
        foreach ($logs as $i => $log) {
            $statusIcon = match ($log->status) {
                'success' => '<fg=green>✓</>',
                'failed' => '<fg=red>✗</>',
                'skipped' => '<fg=gray>↷</>',
                default => '<fg=yellow>•</>',
            };
            $duration = $log->duration_ms !== null ? "{$log->duration_ms}ms" : '—';
            $output = $log->output_payload ?? [];
            $summary = $this->summarizeOutput($output);

            $rows[] = [
                ($i + 1),
                "{$statusIcon} {$log->agent_name}",
                $duration,
                $summary,
            ];
        }

        $this->table(['#', 'Agent', 'Duration', 'Output (key fields)'], $rows);
    }

    private function summarizeOutput(array $output): string
    {
        if (empty($output)) {
            return '—';
        }

        $keys = ['is_ad', 'confidence', 'is_violation', 'category', 'violation_severity', 'reason'];
        $parts = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $output)) {
                $v = $output[$k];
                $v = is_bool($v) ? ($v ? 'true' : 'false') : (is_scalar($v) ? (string) $v : json_encode($v));
                $parts[] = "{$k}={$v}";
            }
        }

        if (empty($parts)) {
            $first = array_slice($output, 0, 2, true);
            foreach ($first as $k => $v) {
                $v = is_scalar($v) ? (string) $v : json_encode($v);
                $parts[] = "{$k}={$v}";
            }
        }

        return implode(', ', array_slice($parts, 0, 3));
    }

    private function printResult(Message $message, int $totalMs): void
    {
        $this->line('');
        $this->line('<fg=yellow;options=bold>📊 PIPELINE RESULT</>');
        $this->line(str_repeat('─', 70));

        $message->refresh();
        $this->line(sprintf('  Message status     : is_ad=%s, is_violation=%s, processed=%s',
            $message->is_ad ? '<fg=green>true</>' : '<fg=gray>false</>',
            ($message->violation_detected ?? false) ? '<fg=red>true</>' : '<fg=gray>false</>',
            $message->is_processed ? '<fg=green>yes</>' : '<fg=red>no</>',
        ));

        $listing = $message->listing;
        if ($listing) {
            $this->line('');
            $this->line('  <fg=green>✅ Listing Created</>');
            $this->line("    id          : <fg=cyan>{$listing->id}</>");
            $this->line("    title       : {$listing->title}");
            $this->line(sprintf('    price       : %s',
                $listing->price ? 'Rp ' . number_format($listing->price, 0, ',', '.') : ($listing->price_label ?? '—')));
            $this->line(sprintf('    contact     : %s', $listing->contact_number ?? '—'));
            $this->line(sprintf('    location    : %s', $listing->location ?? '—'));
            $this->line(sprintf('    permanent   : %s/p/%d', config('app.url'), $listing->id));
        } else {
            $this->line('  <fg=gray>(no listing extracted — sesuai expected untuk skenario non-iklan)</>');
        }

        $agentCount = AgentLog::where('message_id', $message->id)->count();
        $this->line('');
        $this->line(sprintf('  <fg=cyan>⏱  Total: %dms across %d agent calls</>', $totalMs, $agentCount));
        $this->line('');
    }

    private function cleanup(Message $message): void
    {
        $this->line('<fg=gray>🧹 Cleaning up demo data...</>');
        Listing::where('message_id', $message->id)->forceDelete();
        AgentLog::where('message_id', $message->id)->delete();
        $message->forceDelete();
        $this->line('<fg=gray>   Done.</>');
    }
}
