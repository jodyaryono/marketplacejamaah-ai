<?php

namespace App\Http\Controllers;

use App\Models\AgentLog;
use App\Models\Setting;
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

    /**
     * Per-model published pricing (USD per 1M tokens). Used to estimate per-model
     * cost on the AI Health page. Keyed by "<provider>|<model>".
     * Sources: ai.google.dev/pricing, console.groq.com/pricing (May 2026 snapshot).
     * For unknown/new models we fall back to MODEL_PRICING_FALLBACK.
     */
    private const MODEL_PRICING = [
        // Gemini Flash family — same per-token pricing for text and image input
        'gemini|gemini-flash-latest'             => ['in' => 0.075, 'out' => 0.30],
        'gemini|gemini-2.5-flash'                => ['in' => 0.075, 'out' => 0.30],
        'gemini|gemini-2.5-flash-preview'        => ['in' => 0.075, 'out' => 0.30],
        'gemini|gemini-2.0-flash'                => ['in' => 0.10,  'out' => 0.40],
        'gemini|gemini-2.0-flash-001'            => ['in' => 0.10,  'out' => 0.40],
        'gemini|gemini-2.0-flash-exp'            => ['in' => 0.0,   'out' => 0.0], // free experimental
        'gemini|gemini-1.5-flash'                => ['in' => 0.075, 'out' => 0.30],
        'gemini|gemini-1.5-flash-8b'             => ['in' => 0.0375,'out' => 0.15],
        'gemini|gemini-1.5-pro'                  => ['in' => 1.25,  'out' => 5.00],
        'gemini|gemini-2.5-pro'                  => ['in' => 1.25,  'out' => 10.00],
        // Groq Llama text
        'groq|llama-3.3-70b-versatile'           => ['in' => 0.59,  'out' => 0.79],
        'groq|llama-3.1-70b-versatile'           => ['in' => 0.59,  'out' => 0.79],
        'groq|llama-3.1-8b-instant'              => ['in' => 0.05,  'out' => 0.08],
        // Groq Llama 4 vision
        'groq|meta-llama/llama-4-scout-17b-16e-instruct'    => ['in' => 0.11, 'out' => 0.34],
        'groq|meta-llama/llama-4-maverick-17b-128e-instruct'=> ['in' => 0.20, 'out' => 0.60],
    ];

    /** Used when an unknown model appears in cache (e.g. user changed GEMINI_MODEL). */
    private const MODEL_PRICING_FALLBACK = ['in' => 0.075, 'out' => 0.30];

    public function index()
    {
        // ── 1. Gemini config ──────────────────────────────────────────────
        // DB settings take precedence; .env is fallback. Source label tells the
        // user where the active key/model is loaded from so they can match it
        // to billing UUIDs in Google AI Studio.
        $dbGeminiKey   = Setting::get('gemini_api_key');
        $dbGeminiModel = Setting::get('gemini_model');
        $geminiModel   = $dbGeminiModel ?: config('services.gemini.model', '-');
        $geminiKeyRaw  = $dbGeminiKey   ?: (string) config('services.gemini.api_key', '');
        $geminiKeySource = !empty($dbGeminiKey)
            ? 'tabel settings (terenkripsi)'
            : (!empty(config('services.gemini.api_key')) ? 'fallback dari .env' : 'tidak dikonfigurasikan');
        $geminiKeyMasked = Setting::masked($geminiKeyRaw, '(tidak dikonfigurasikan)');

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

        // ── 2b. Per-model breakdown (last 7 days) ─────────────────────────
        // Aggregate ai_model_usage_YYYY-MM-DD daily caches into a single
        // table grouped by provider|model|type so the page can show which
        // model is driving cost (Gemini text vs vision vs Groq fallbacks).
        $modelAgg = [];
        $geminiModelName = config('services.gemini.model', 'gemini-flash-latest');
        $groqModelName   = config('services.groq.model', 'llama-3.3-70b-versatile');
        $groqVisionName  = config('services.groq.vision_model', 'meta-llama/llama-4-scout-17b-16e-instruct');

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();

            // PRIMARY: precise per-model tracking (added in this commit)
            $day = Cache::get('ai_model_usage_' . $date, []);
            $hasPerModelData = is_array($day) && !empty($day);
            if ($hasPerModelData) {
                foreach ($day as $bucketKey => $row) {
                    if (!isset($modelAgg[$bucketKey])) {
                        $modelAgg[$bucketKey] = [
                            'provider'      => $row['provider'] ?? '?',
                            'model'         => $row['model'] ?? '?',
                            'type'          => $row['type'] ?? 'text',
                            'calls'         => 0,
                            'prompt_tokens' => 0,
                            'output_tokens' => 0,
                        ];
                    }
                    $modelAgg[$bucketKey]['calls']         += (int) ($row['calls'] ?? 0);
                    $modelAgg[$bucketKey]['prompt_tokens'] += (int) ($row['prompt_tokens'] ?? 0);
                    $modelAgg[$bucketKey]['output_tokens'] += (int) ($row['output_tokens'] ?? 0);
                }
                continue;
            }

            // FALLBACK: derive from legacy aggregated caches so the table is
            // populated immediately on first deploy. The legacy gemini_usage_*
            // bucket lumps text+image tokens together; we approximate by
            // splitting based on the image_calls / total_calls ratio. This is
            // an estimate, not exact — flagged with has_pricing=false in UI.
            $geminiDay = Cache::get('gemini_usage_' . $date, null);
            if (is_array($geminiDay) && ($geminiDay['calls'] ?? 0) > 0) {
                $totalCalls = max(1, (int) $geminiDay['calls']);
                $imageCalls = (int) ($geminiDay['image_calls'] ?? 0);
                $textCalls  = max(0, $totalCalls - $imageCalls);
                $imageRatio = $imageCalls / $totalCalls;
                $textRatio  = 1 - $imageRatio;
                $promptTok  = (int) ($geminiDay['prompt_tokens'] ?? 0);
                $outputTok  = (int) ($geminiDay['output_tokens'] ?? 0);

                if ($textCalls > 0) {
                    $tk = "gemini|{$geminiModelName}|text";
                    $modelAgg[$tk] = $modelAgg[$tk] ?? ['provider' => 'gemini', 'model' => $geminiModelName, 'type' => 'text', 'calls' => 0, 'prompt_tokens' => 0, 'output_tokens' => 0];
                    $modelAgg[$tk]['calls']         += $textCalls;
                    $modelAgg[$tk]['prompt_tokens'] += (int) round($promptTok * $textRatio);
                    $modelAgg[$tk]['output_tokens'] += (int) round($outputTok * $textRatio);
                }
                if ($imageCalls > 0) {
                    $ik = "gemini|{$geminiModelName}|image";
                    $modelAgg[$ik] = $modelAgg[$ik] ?? ['provider' => 'gemini', 'model' => $geminiModelName, 'type' => 'image', 'calls' => 0, 'prompt_tokens' => 0, 'output_tokens' => 0];
                    $modelAgg[$ik]['calls']         += $imageCalls;
                    $modelAgg[$ik]['prompt_tokens'] += (int) round($promptTok * $imageRatio);
                    $modelAgg[$ik]['output_tokens'] += (int) round($outputTok * $imageRatio);
                }
            }

            $groqDay = Cache::get('groq_usage_' . $date, null);
            if (is_array($groqDay) && ($groqDay['calls'] ?? 0) > 0) {
                $totalCalls = max(1, (int) $groqDay['calls']);
                $imageCalls = (int) ($groqDay['image_calls'] ?? 0);
                $textCalls  = max(0, $totalCalls - $imageCalls);
                $imageRatio = $imageCalls / $totalCalls;
                $textRatio  = 1 - $imageRatio;
                $promptTok  = (int) ($groqDay['prompt_tokens'] ?? 0);
                $outputTok  = (int) ($groqDay['output_tokens'] ?? 0);

                if ($textCalls > 0) {
                    $tk = "groq|{$groqModelName}|text";
                    $modelAgg[$tk] = $modelAgg[$tk] ?? ['provider' => 'groq', 'model' => $groqModelName, 'type' => 'text', 'calls' => 0, 'prompt_tokens' => 0, 'output_tokens' => 0];
                    $modelAgg[$tk]['calls']         += $textCalls;
                    $modelAgg[$tk]['prompt_tokens'] += (int) round($promptTok * $textRatio);
                    $modelAgg[$tk]['output_tokens'] += (int) round($outputTok * $textRatio);
                }
                if ($imageCalls > 0) {
                    $ik = "groq|{$groqVisionName}|image";
                    $modelAgg[$ik] = $modelAgg[$ik] ?? ['provider' => 'groq', 'model' => $groqVisionName, 'type' => 'image', 'calls' => 0, 'prompt_tokens' => 0, 'output_tokens' => 0];
                    $modelAgg[$ik]['calls']         += $imageCalls;
                    $modelAgg[$ik]['prompt_tokens'] += (int) round($promptTok * $imageRatio);
                    $modelAgg[$ik]['output_tokens'] += (int) round($outputTok * $imageRatio);
                }
            }
        }
        // Always seed the 4 known models even when no usage yet, so the page
        // shows which models the system uses (vs. blank "Belum ada data").
        $expectedModels = [
            "gemini|{$geminiModelName}|text"  => ['provider' => 'gemini', 'model' => $geminiModelName, 'type' => 'text'],
            "gemini|{$geminiModelName}|image" => ['provider' => 'gemini', 'model' => $geminiModelName, 'type' => 'image'],
            "groq|{$groqModelName}|text"      => ['provider' => 'groq',   'model' => $groqModelName,   'type' => 'text'],
            "groq|{$groqVisionName}|image"    => ['provider' => 'groq',   'model' => $groqVisionName,  'type' => 'image'],
        ];
        foreach ($expectedModels as $bucketKey => $stub) {
            if (!isset($modelAgg[$bucketKey])) {
                $modelAgg[$bucketKey] = $stub + [
                    'calls'         => 0,
                    'prompt_tokens' => 0,
                    'output_tokens' => 0,
                ];
            }
        }

        // Compute per-model cost using published pricing
        $modelBreakdown = [];
        $modelTotalCostUsd = 0.0;
        foreach ($modelAgg as $row) {
            $priceKey   = $row['provider'] . '|' . $row['model'];
            $price      = self::MODEL_PRICING[$priceKey] ?? self::MODEL_PRICING_FALLBACK;
            $costUsd    = round(
                ($row['prompt_tokens'] / 1_000_000 * $price['in'])
                    + ($row['output_tokens'] / 1_000_000 * $price['out']),
                6
            );
            $row['price_in_per_m']  = $price['in'];
            $row['price_out_per_m'] = $price['out'];
            $row['cost_usd']        = $costUsd;
            $row['cost_idr']        = (int) ($costUsd * self::IDR_RATE);
            $row['total_tokens']    = $row['prompt_tokens'] + $row['output_tokens'];
            $row['has_pricing']     = isset(self::MODEL_PRICING[$priceKey]);
            $modelBreakdown[]       = $row;
            $modelTotalCostUsd     += $costUsd;
        }
        // Sort by cost desc so the most expensive model is on top
        usort($modelBreakdown, fn($a, $b) => $b['cost_usd'] <=> $a['cost_usd']);
        $modelTotalCostIdr = (int) ($modelTotalCostUsd * self::IDR_RATE);

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
            'geminiModel', 'geminiKeyMasked', 'geminiKeySource',
            'tokenDays', 'totalCalls', 'totalImageCalls',
            'totalPrompt', 'totalOutput', 'totalCostUsd', 'totalCostIdr',
            'modelBreakdown', 'modelTotalCostUsd', 'modelTotalCostIdr',
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
        // Effective config — DB first, .env fallback (mirrors GeminiService::__construct)
        $effGeminiModel       = Setting::get('gemini_model')        ?: config('services.gemini.model', '-');
        $effGeminiApiKey      = Setting::get('gemini_api_key')      ?: config('services.gemini.api_key');
        $effGroqVisionModel   = Setting::get('groq_vision_model')   ?: config('services.groq.vision_model', '-');
        $effGroqApiKey        = Setting::get('groq_api_key')        ?: config('services.groq.api_key');

        $models = [
            $this->pingGeminiText() + ['provider' => 'Gemini', 'role' => 'primary text', 'type' => 'text'],
            $this->pingGroqText($gemini) + ['provider' => 'Groq', 'role' => 'fallback text', 'type' => 'text'],
            [
                'provider' => 'Gemini',
                'role' => 'vision (image analysis)',
                'type' => 'vision',
                'model' => $effGeminiModel,
                'ok' => null, // not pinged — informational only
                'configured' => !empty($effGeminiApiKey),
            ],
            [
                'provider' => 'Groq',
                'role' => 'vision fallback',
                'type' => 'vision',
                'model' => $effGroqVisionModel,
                'ok' => null,
                'configured' => !empty($effGroqApiKey),
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
        $model = Setting::get('gemini_model') ?: config('services.gemini.model', 'gemini-flash-latest');
        try {
            $apiKey = Setting::get('gemini_api_key') ?: config('services.gemini.api_key');
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
