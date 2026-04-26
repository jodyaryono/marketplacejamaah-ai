<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;
use App\Services\GeminiService;
use App\Services\WhacenterService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiHealthController extends Controller
{
    // Gemini Flash 2.0 pricing (USD per 1M tokens)
    private const PRICE_INPUT_PER_M = 0.075;
    private const PRICE_OUTPUT_PER_M = 0.3;
    // Rough exchange rate IDR/USD
    private const IDR_RATE = 16000;

    public function index()
    {
        // ── 1. Gemini config ──────────────────────────────────────────────
        $geminiModel = config('services.gemini.model', '-');
        $geminiKeyRaw = config('services.gemini.api_key', '');
        $geminiKeyMasked = $geminiKeyRaw
            ? substr($geminiKeyRaw, 0, 6) . str_repeat('*', max(0, strlen($geminiKeyRaw) - 10)) . substr($geminiKeyRaw, -4)
            : '(tidak dikonfigurasikan)';

        // ── 2. Token usage last 7 days (from daily cache) ─────────────────
        $tokenDays = [];
        $totalCalls = $totalPrompt = $totalOutput = $totalImageCalls = 0;
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $day = Cache::get('gemini_usage_' . $date, [
                'calls' => 0,
                'image_calls' => 0,
                'prompt_tokens' => 0,
                'output_tokens' => 0,
            ]);
            $day['date'] = $date;
            $day['label'] = now()->subDays($i)->format('d/m');
            $day['cost_usd'] = round(
                ($day['prompt_tokens'] / 1_000_000 * self::PRICE_INPUT_PER_M)
                    + ($day['output_tokens'] / 1_000_000 * self::PRICE_OUTPUT_PER_M),
                6
            );
            $day['cost_idr'] = (int) ($day['cost_usd'] * self::IDR_RATE);
            $tokenDays[] = $day;
            $totalCalls += $day['calls'];
            $totalImageCalls += $day['image_calls'];
            $totalPrompt += $day['prompt_tokens'];
            $totalOutput += $day['output_tokens'];
        }

        $totalCostUsd = round(
            ($totalPrompt / 1_000_000 * self::PRICE_INPUT_PER_M)
                + ($totalOutput / 1_000_000 * self::PRICE_OUTPUT_PER_M),
            4
        );
        $totalCostIdr = (int) ($totalCostUsd * self::IDR_RATE);

        // ── 3. Per-agent stats (last 7 days) ──────────────────────────────
        $agentStats = AgentLog::select('agent_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN status='success' THEN 1 END) as success"),
                DB::raw("COUNT(CASE WHEN status='failed' THEN 1 END) as failed"),
                DB::raw("COUNT(CASE WHEN status='skipped' THEN 1 END) as skipped"),
                DB::raw('ROUND(AVG(duration_ms)) as avg_ms'),
                DB::raw('MAX(created_at) as last_run'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('agent_name')
            ->orderBy('agent_name')
            ->get()
            ->map(function ($row) {
                $row->success_rate = $row->total > 0
                    ? round($row->success / $row->total * 100)
                    : 0;
                return $row;
            });

        // ── 4. WhaCentre status (last known from DB or config) ─────────────
        $waUrl = config('services.wa_gateway.url', '-');

        // ── 5. Queue status: count stuck processing jobs > 2min ───────────
        $stuckJobs = AgentLog::where('status', 'processing')
            ->where('created_at', '<', now()->subMinutes(2))
            ->count();

        // ── 6. Cached last ping results ────────────────────────────────────
        $lastGeminiPing = Cache::get('health_gemini_last');
        $lastWhacenterPing = Cache::get('health_whacenter_last');
        $lastDbPing = Cache::get('health_db_last');
        $lastQueuePing = Cache::get('health_queue_last');
        $lastSystemPing = Cache::get('health_system_last');

        return view('ai-health.index', compact(
            'geminiModel', 'geminiKeyMasked',
            'tokenDays', 'totalCalls', 'totalImageCalls',
            'totalPrompt', 'totalOutput', 'totalCostUsd', 'totalCostIdr',
            'agentStats', 'waUrl', 'stuckJobs',
            'lastGeminiPing', 'lastWhacenterPing',
            'lastDbPing', 'lastQueuePing', 'lastSystemPing'
        ));
    }

    /**
     * AJAX: send a tiny test prompt to Gemini and return latency + status.
     */
    public function pingGemini(GeminiService $gemini)
    {
        // Ping all configured AI models — primary text (Gemini) + fallback text (Groq).
        // Vision models are listed but not pinged (they need image input + cost more).
        $models = [
            $this->pingGeminiText() + ['provider' => 'Gemini', 'role' => 'primary text', 'type' => 'text'],
            $this->pingGroqText($gemini) + ['provider' => 'Groq', 'role' => 'fallback text', 'type' => 'text'],
            [
                'provider' => 'Gemini',
                'role' => 'vision (image analysis)',
                'type' => 'vision',
                'model' => config('services.gemini.model', '-'),
                'ok' => null, // not pinged — informational only
                'configured' => !empty(config('services.gemini.api_key')),
            ],
            [
                'provider' => 'Groq',
                'role' => 'vision fallback',
                'type' => 'vision',
                'model' => config('services.groq.vision_model', '-'),
                'ok' => null,
                'configured' => !empty(config('services.groq.api_key')),
            ],
        ];

        // Card is green if at least one TEXT model works (system can still respond).
        $textOk = collect($models)->where('type', 'text')->contains(fn($m) => $m['ok'] === true);

        $payload = [
            'ok' => $textOk,
            'models' => $models,
            'at' => now()->format('H:i:s'),
        ];
        Cache::put('health_gemini_last', $payload, now()->addHours(6));
        return response()->json($payload);
    }

    /**
     * Direct HTTP ping to Gemini text endpoint (no fallback, no caching).
     */
    private function pingGeminiText(): array
    {
        $start = microtime(true);
        $model = config('services.gemini.model', 'gemini-flash-latest');
        try {
            $apiKey = config('services.gemini.api_key');
            $endpoint = rtrim(config('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
            $url = "{$endpoint}/{$model}:generateContent";
            $resp = Http::timeout(15)->post($url . '?key=' . urlencode($apiKey), [
                'contents' => [['parts' => [['text' => 'Reply with the single word: PONG']]]],
                'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 16],
            ]);
            $ms = (int) ((microtime(true) - $start) * 1000);
            if ($resp->failed()) {
                return ['model' => $model, 'ok' => false, 'latency' => $ms, 'error' => 'HTTP ' . $resp->status() . ': ' . mb_substr(strip_tags($resp->body()), 0, 120)];
            }
            $text = trim($resp->json('candidates.0.content.parts.0.text') ?? '');
            return ['model' => $model, 'ok' => str_contains(strtolower($text), 'pong'), 'latency' => $ms, 'response' => $text, 'error' => null];
        } catch (\Throwable $e) {
            return ['model' => $model, 'ok' => false, 'latency' => (int)((microtime(true) - $start) * 1000), 'error' => $e->getMessage()];
        }
    }

    private function pingGroqText(GeminiService $gemini): array
    {
        $start = microtime(true);
        $r = $gemini->pingGroqFallback();
        $ms = (int) ((microtime(true) - $start) * 1000);
        return ['model' => $r['model'] ?? config('services.groq.model'), 'ok' => $r['ok'], 'latency' => $ms, 'response' => $r['response'] ?? '', 'error' => $r['error']];
    }

    /**
     * AJAX: hit the WhaCentre gateway health endpoint and return status + session details.
     */
    public function pingWhacenter()
    {
        $base = rtrim(config('services.wa_gateway.url'), '/');
        $token = config('services.wa_gateway.token', '');
        $start = microtime(true);

        try {
            $response = Http::timeout(8)
                ->withHeaders(['Authorization' => "Bearer {$token}"])
                ->get($base . '/status');
            $ms = (int) ((microtime(true) - $start) * 1000);
            $ok = $response->successful();

            // Parse per-session data from /api/status
            $body = $response->json() ?? [];
            $sessions = [];
            if ($ok && isset($body['sessions']) && is_array($body['sessions'])) {
                foreach ($body['sessions'] as $phoneId => $s) {
                    $sessions[] = [
                        'phone_id' => $phoneId,
                        'label' => $s['label'] ?? $phoneId,
                        'status' => $s['status'] ?? 'unknown',
                        'groups_cached' => $s['groups_cached'] ?? 0,
                    ];
                }
            }

            $payload = [
                'ok' => $ok,
                'latency' => $ms,
                'http' => $response->status(),
                'sessions' => $sessions,
                'uptime' => $body['uptime'] ?? null,
                'at' => now()->format('H:i:s'),
            ];
            Cache::put('health_whacenter_last', $payload, now()->addHours(6));
            return response()->json($payload);
        } catch (\Throwable $e) {
            $ms = (int) ((microtime(true) - $start) * 1000);
            $payload = [
                'ok' => false,
                'latency' => $ms,
                'error' => $e->getMessage(),
                'sessions' => [],
                'at' => now()->format('H:i:s'),
            ];
            Cache::put('health_whacenter_last', $payload, now()->addHours(6));
            return response()->json($payload, 500);
        }
    }

    /**
     * AJAX: test database connectivity and return stats.
     */
    public function pingDatabase()
    {
        $start = microtime(true);
        try {
            $row = DB::select('SELECT version() as v, pg_database_size(current_database()) as size')[0];
            $ms = (int) ((microtime(true) - $start) * 1000);

            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            $payload = [
                'ok' => true,
                'latency' => $ms,
                'version' => preg_replace('/\s+/', ' ', $row->v),
                'size_mb' => round($row->size / 1024 / 1024, 1),
                'pending_jobs' => (int) $pending,
                'failed_jobs' => (int) $failed,
                'at' => now()->format('H:i:s'),
            ];
            Cache::put('health_db_last', $payload, now()->addHours(6));
            return response()->json($payload);
        } catch (\Throwable $e) {
            $payload = ['ok' => false, 'error' => $e->getMessage(), 'at' => now()->format('H:i:s')];
            Cache::put('health_db_last', $payload, now()->addHours(6));
            return response()->json($payload, 500);
        }
    }

    /**
     * AJAX: return real-time queue depth and recent agent log stats.
     */
    public function pingQueue()
    {
        $start = microtime(true);
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();
            // Recent failures are the actionable signal; stale failed_jobs rows
            // shouldn't keep the indicator red forever.
            $recentFailed = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHour())
                ->count();
            $stuck = AgentLog::where('status', 'processing')
                ->where('created_at', '<', now()->subMinutes(5))
                ->count();
            $successH = AgentLog::where('status', 'success')
                ->where('created_at', '>=', now()->subHour())
                ->count();
            // Worker is alive if it processed something recently OR has nothing to do.
            $workerAlive = $successH > 0 || $pending === 0;
            $ms = (int) ((microtime(true) - $start) * 1000);

            $payload = [
                'ok' => $workerAlive && $stuck === 0 && $recentFailed === 0,
                'latency' => $ms,
                'pending' => (int) $pending,
                'failed' => (int) $failed,
                'recent_failed' => (int) $recentFailed,
                'stuck' => (int) $stuck,
                'success_last_1h' => (int) $successH,
                'at' => now()->format('H:i:s'),
            ];
            Cache::put('health_queue_last', $payload, now()->addHours(6));
            return response()->json($payload);
        } catch (\Throwable $e) {
            $payload = ['ok' => false, 'error' => $e->getMessage(), 'at' => now()->format('H:i:s')];
            Cache::put('health_queue_last', $payload, now()->addHours(6));
            return response()->json($payload, 500);
        }
    }

    /**
     * AJAX: check supervisor process statuses and disk/load.
     */
    public function pingSystem()
    {
        $start = microtime(true);
        try {
            $processes = [];
            $source = 'none';

            // Strategy 1: try supervisorctl. May fail if PHP-FPM user (www-data)
            // lacks access to /var/run/supervisor.sock — supervisorctl prints
            // "error: <class 'PermissionError'>..." to STDOUT (not stderr) in
            // some versions, so we detect that pattern explicitly and skip.
            $raw = trim(@shell_exec('supervisorctl status 2>/dev/null') ?? '');
            $isSupervisorError = $raw === ''
                || str_starts_with($raw, 'error:')
                || str_contains($raw, 'PermissionError')
                || str_contains($raw, 'refused connection');

            if (!$isSupervisorError) {
                foreach (explode("\n", $raw) as $line) {
                    if (!trim($line)) {
                        continue;
                    }
                    if (preg_match('/^(\S+)\s+(\S+)\s*(.*)$/', $line, $m)) {
                        $processes[] = [
                            'name' => $m[1],
                            'status' => $m[2],
                            'detail' => trim($m[3]),
                        ];
                    }
                }
                if (!empty($processes)) {
                    $source = 'supervisor';
                }
            }

            // Strategy 2: fall back to ps if supervisorctl returned nothing.
            // We look for the workers we actually care about by command line.
            if (empty($processes)) {
                $psRaw = @shell_exec('ps -eo pid,args 2>/dev/null') ?? '';
                $patterns = [
                    'queue-worker' => '/artisan\s+queue:work/',
                    'reverb' => '/artisan\s+reverb:start/',
                    'scheduler' => '/artisan\s+schedule:(run|work)/',
                ];
                $found = array_fill_keys(array_keys($patterns), 0);
                foreach (explode("\n", $psRaw) as $line) {
                    foreach ($patterns as $name => $rx) {
                        if (preg_match($rx, $line)) {
                            $found[$name]++;
                        }
                    }
                }
                foreach ($found as $name => $count) {
                    $processes[] = [
                        'name' => $name,
                        'status' => $count > 0 ? 'RUNNING' : 'STOPPED',
                        'detail' => $count > 0 ? "{$count} proses" : 'tidak terdeteksi',
                    ];
                }
                if ($psRaw !== '') {
                    $source = 'ps';
                }
            }

            // Disk usage on /
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskUsed = $diskTotal > 0 ? round(($diskTotal - $diskFree) / $diskTotal * 100) : 0;

            // System load
            $load = sys_getloadavg();

            $ms = (int) ((microtime(true) - $start) * 1000);

            // Determine "ok": at least the queue worker should be running.
            // For supervisor source, every app process must be RUNNING.
            // For ps source, queue-worker must be RUNNING (reverb is optional).
            if ($source === 'supervisor') {
                $appProcs = array_filter(
                    $processes,
                    fn($p) => str_contains($p['name'], 'marketplacejamaah') || $p['name'] === 'integrasi-wa'
                );
                $allOk = count($appProcs) > 0
                    && count(array_filter($appProcs, fn($p) => $p['status'] !== 'RUNNING')) === 0;
            } elseif ($source === 'ps') {
                $queueRunning = collect($processes)
                    ->first(fn($p) => $p['name'] === 'queue-worker' && $p['status'] === 'RUNNING');
                $allOk = (bool) $queueRunning;
            } else {
                // Couldn't run ps either — likely shell_exec disabled. Don't
                // claim red just because we can't introspect; surface as unknown.
                $allOk = true;
                $processes = [['name' => '(introspeksi tidak tersedia)', 'status' => 'UNKNOWN', 'detail' => 'shell_exec disabled atau supervisor tidak terpasang']];
            }

            $payload = [
                'ok' => $allOk,
                'latency' => $ms,
                'processes' => $processes,
                'source' => $source,
                'disk_free_gb' => round($diskFree / 1073741824, 1),
                'disk_used_pct' => $diskUsed,
                'load_1m' => $load[0] ?? null,
                'at' => now()->format('H:i:s'),
            ];
            Cache::put('health_system_last', $payload, now()->addHours(6));
            return response()->json($payload);
        } catch (\Throwable $e) {
            $payload = ['ok' => false, 'error' => $e->getMessage(), 'at' => now()->format('H:i:s')];
            Cache::put('health_system_last', $payload, now()->addHours(6));
            return response()->json($payload, 500);
        }
    }
}
