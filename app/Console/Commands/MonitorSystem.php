<?php

namespace App\Console\Commands;

use App\Services\WhacenterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorSystem extends Command
{
    protected $signature = 'monitor:run {--force : Send report even if no problems}';
    protected $description = 'Proactive system monitoring — checks WA gateway, server resources, queue health, and alerts admin via WhatsApp';

    private const ADMIN_PHONE = '6285719195627';
    private const ADMIN_EMAIL = 'me@jodyaryono.id';
    private const CACHE_PREFIX = 'monitor:';
    private const COOLDOWN_MINUTES = 1440;  // Don't spam same alert within 1 day

    public function handle(WhacenterService $wa): int
    {
        $problems = [];
        $metrics = [];

        // 1. WA Gateway health (+ auto-recovery if coming back from downtime)
        $this->checkGateway($problems, $metrics);
        $this->autoRecoverIfNeeded($metrics);

        // 2. Server resources
        $this->checkDisk($problems, $metrics);
        $this->checkMemory($problems, $metrics);
        $this->checkCpu($problems, $metrics);

        // 3. Queue health
        $this->checkQueue($problems, $metrics);

        // 4. Database health
        $this->checkDatabase($problems, $metrics);

        // 5. Recent error rate
        $this->checkAgentErrors($problems, $metrics);

        // 6. Supervisor processes
        $this->checkSupervisor($problems, $metrics);

        if (empty($problems) && !$this->option('force')) {
            $this->info('All checks passed.');
            // Log healthy status once per hour
            if (!Cache::has(self::CACHE_PREFIX . 'healthy_logged')) {
                Log::info('MonitorSystem: all checks OK', $metrics);
                Cache::put(self::CACHE_PREFIX . 'healthy_logged', true, now()->addHour());
            }
            return self::SUCCESS;
        }

        // Build alert message
        $msg = $this->buildAlertMessage($problems, $metrics);

        if ($this->option('force') && empty($problems)) {
            $msg = "✅ *SYSTEM HEALTH REPORT*\n"
                . '🕐 ' . now()->format('d/m/Y H:i') . " WIB\n\n"
                . "Semua sistem berjalan normal.\n\n"
                . $this->formatMetrics($metrics);
        }

        // Check cooldown to avoid alert spam
        $alertHash = md5(implode('|', array_column($problems, 'key')));
        $cooldownKey = self::CACHE_PREFIX . 'alert:' . $alertHash;
        if (Cache::has($cooldownKey) && !$this->option('force')) {
            $this->warn('Alert already sent within cooldown period. Skipping.');
            return self::SUCCESS;
        }

        try {
            $wa->sendMessage(self::ADMIN_PHONE, $msg);
            $this->warn('WA alert sent to ' . self::ADMIN_PHONE);
        } catch (\Throwable $e) {
            Log::error('MonitorSystem: failed to send WA alert', ['error' => $e->getMessage()]);
            $this->error('Failed to send WA alert: ' . $e->getMessage());
        }

        // Send email alert
        try {
            $plainMsg = str_replace(['*', '_'], '', $msg);
            $subject = empty($problems)
                ? '[MarketplaceJamaah] System Health Report'
                : '[MarketplaceJamaah] System Alert - ' . count($problems) . ' issue(s)';
            Mail::raw($plainMsg, function ($m) use ($subject) {
                $m->to(self::ADMIN_EMAIL)->subject($subject);
            });
            $this->warn('Email alert sent to ' . self::ADMIN_EMAIL);
        } catch (\Throwable $e) {
            Log::error('MonitorSystem: failed to send email', ['error' => $e->getMessage()]);
            $this->error('Failed to send email: ' . $e->getMessage());
        }

        Cache::put($cooldownKey, true, now()->addMinutes(self::COOLDOWN_MINUTES));
        Log::warning('MonitorSystem: alert sent', ['problems' => $problems]);

        return empty($problems) ? self::SUCCESS : self::FAILURE;
    }

    private function checkGateway(array &$problems, array &$metrics): void
    {
        try {
            $baseUrl = rtrim(config('services.wa_gateway.url'), '/');
            $token = config('services.wa_gateway.token');
            $phoneId = config('services.wa_gateway.phone_id');

            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$baseUrl}/status");

            if ($response->failed()) {
                $problems[] = ['key' => 'wa_api_down', 'msg' => "WA Gateway API error (HTTP {$response->status()})"];
                $metrics['wa_gateway'] = 'API error ' . $response->status();
                return;
            }

            $data = $response->json();
            $sessions = $data['sessions'] ?? [];
            $session = $sessions[$phoneId] ?? null;
            $uptime = isset($data['uptime']) ? round($data['uptime'] / 3600, 1) . 'h' : '?';
            $status = $session['status'] ?? 'not_found';
            $metrics['wa_gateway'] = "{$status} (uptime: {$uptime})";

            if ($status !== 'open') {
                $problems[] = ['key' => 'wa_disconnected', 'msg' => "WA Gateway session *{$phoneId}* status: *{$status}* (bukan open)"];
            }
        } catch (\Throwable $e) {
            $problems[] = ['key' => 'wa_unreachable', 'msg' => 'WA Gateway tidak bisa dihubungi: ' . mb_substr($e->getMessage(), 0, 100)];
            $metrics['wa_gateway'] = 'unreachable';
        }
    }

    private function checkDisk(array &$problems, array &$metrics): void
    {
        try {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $usedPct = round((1 - $free / $total) * 100, 1);
            $freeGb = round($free / 1073741824, 1);
            $metrics['disk'] = "{$usedPct}% used ({$freeGb}GB free)";

            if ($usedPct > 90) {
                $problems[] = ['key' => 'disk_critical', 'msg' => "Disk kritis: {$usedPct}% terpakai (sisa {$freeGb}GB)"];
            } elseif ($usedPct > 80) {
                $problems[] = ['key' => 'disk_warning', 'msg' => "Disk hampir penuh: {$usedPct}% terpakai (sisa {$freeGb}GB)"];
            }
        } catch (\Throwable $e) {
            $metrics['disk'] = 'check failed';
        }
    }

    private function checkMemory(array &$problems, array &$metrics): void
    {
        try {
            $meminfo = @file_get_contents('/proc/meminfo');
            if (!$meminfo) {
                $metrics['memory'] = 'N/A';
                return;
            }

            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);

            if (!empty($total[1]) && !empty($available[1])) {
                $totalMb = round($total[1] / 1024);
                $availMb = round($available[1] / 1024);
                $usedPct = round((1 - $available[1] / $total[1]) * 100, 1);
                $metrics['memory'] = "{$usedPct}% used ({$availMb}MB free of {$totalMb}MB)";

                if ($usedPct > 95) {
                    $problems[] = ['key' => 'mem_critical', 'msg' => "RAM kritis: {$usedPct}% terpakai (sisa {$availMb}MB)"];
                } elseif ($usedPct > 85) {
                    $problems[] = ['key' => 'mem_warning', 'msg' => "RAM tinggi: {$usedPct}% terpakai (sisa {$availMb}MB)"];
                }
            }
        } catch (\Throwable $e) {
            $metrics['memory'] = 'check failed';
        }
    }

    private function checkCpu(array &$problems, array &$metrics): void
    {
        try {
            $load = sys_getloadavg();
            if (!$load) {
                $metrics['cpu_load'] = 'N/A';
                return;
            }
            $cores = (int) @trim(shell_exec('nproc 2>/dev/null') ?: '1');
            $load1 = round($load[0], 2);
            $load5 = round($load[1], 2);
            $metrics['cpu_load'] = "{$load1} / {$load5} / " . round($load[2], 2) . " ({$cores} cores)";

            if ($load5 > $cores * 2) {
                $problems[] = ['key' => 'cpu_critical', 'msg' => "CPU load sangat tinggi: {$load5} (5min avg, {$cores} cores)"];
            } elseif ($load5 > $cores * 1.5) {
                $problems[] = ['key' => 'cpu_warning', 'msg' => "CPU load tinggi: {$load5} (5min avg, {$cores} cores)"];
            }
        } catch (\Throwable $e) {
            $metrics['cpu_load'] = 'check failed';
        }
    }

    private function checkQueue(array &$problems, array &$metrics): void
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
            $metrics['queue'] = "{$pending} pending, {$failed} failed (1h)";

            if ($pending > 100) {
                $problems[] = ['key' => 'queue_backlog', 'msg' => "Queue menumpuk: {$pending} jobs pending"];
            }
            if ($failed > 5) {
                $problems[] = ['key' => 'queue_failures', 'msg' => "Queue gagal: {$failed} failed jobs dalam 1 jam terakhir"];
            }
        } catch (\Throwable $e) {
            $problems[] = ['key' => 'queue_error', 'msg' => 'Tidak bisa cek queue: ' . $e->getMessage()];
            $metrics['queue'] = 'check failed';
        }
    }

    private function checkDatabase(array &$problems, array &$metrics): void
    {
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $latency = round((microtime(true) - $start) * 1000, 1);
            $metrics['database'] = "OK ({$latency}ms)";

            if ($latency > 1000) {
                $problems[] = ['key' => 'db_slow', 'msg' => "Database lambat: {$latency}ms latency"];
            }
        } catch (\Throwable $e) {
            $problems[] = ['key' => 'db_down', 'msg' => 'Database DOWN: ' . $e->getMessage()];
            $metrics['database'] = 'DOWN';
        }
    }

    private function checkAgentErrors(array &$problems, array &$metrics): void
    {
        try {
            $recentErrors = DB::table('agent_logs')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subHour())
                ->count();
            $totalRecent = DB::table('agent_logs')
                ->where('created_at', '>=', now()->subHour())
                ->count();
            $errorRate = $totalRecent > 0 ? round($recentErrors / $totalRecent * 100, 1) : 0;
            $metrics['agent_errors'] = "{$recentErrors}/{$totalRecent} ({$errorRate}%)";

            if ($recentErrors > 10) {
                $problems[] = ['key' => 'agent_errors', 'msg' => "Banyak agent error: {$recentErrors} gagal dari {$totalRecent} dalam 1 jam ({$errorRate}%)"];
            }
        } catch (\Throwable $e) {
            $metrics['agent_errors'] = 'check failed';
        }
    }

    private function checkSupervisor(array &$problems, array &$metrics): void
    {
        try {
            $output = @shell_exec('supervisorctl status 2>/dev/null');
            if (!$output) {
                $metrics['supervisor'] = 'N/A';
                return;
            }

            $lines = array_filter(explode("\n", trim($output)));
            // Only monitor processes related to marketplace jamaah
            $ourProcesses = ['marketplacejamaah-', 'integrasi-wa', 'marketplacejamaah-reverb'];
            $downProcesses = [];
            foreach ($lines as $line) {
                if (preg_match('/^(\S+)\s+(FATAL|STOPPED|EXITED|BACKOFF)/', $line, $m)) {
                    $processName = $m[1];
                    $isOurs = false;
                    foreach ($ourProcesses as $prefix) {
                        if (str_starts_with($processName, $prefix)) {
                            $isOurs = true;
                            break;
                        }
                    }
                    if ($isOurs) {
                        $downProcesses[] = $processName . ' (' . $m[2] . ')';
                    }
                }
            }

            $metrics['supervisor'] = count($lines) . ' processes, ' . count($downProcesses) . ' down';

            if (!empty($downProcesses)) {
                $problems[] = ['key' => 'supervisor_down', 'msg' => 'Proses mati: ' . implode(', ', $downProcesses)];
            }
        } catch (\Throwable $e) {
            $metrics['supervisor'] = 'check failed';
        }
    }

    private function buildAlertMessage(array $problems, array $metrics): string
    {
        $severity = $this->getSeverity($problems);
        $icon = match ($severity) {
            'critical' => '🔴',
            'warning' => '🟡',
            default => '🟢',
        };

        $msg = "{$icon} *SYSTEM ALERT — " . strtoupper($severity) . "*\n"
            . '🕐 ' . now()->format('d/m/Y H:i') . " WIB\n\n";

        $msg .= "⚠️ *Masalah ditemukan:*\n";
        foreach ($problems as $i => $p) {
            $msg .= ($i + 1) . ". {$p['msg']}\n";
        }

        $msg .= "\n" . $this->formatMetrics($metrics);

        return $msg;
    }

    private function formatMetrics(array $metrics): string
    {
        $msg = "📊 *Status Lengkap:*\n";
        $labels = [
            'wa_gateway' => '📱 WA Gateway',
            'disk' => '💾 Disk',
            'memory' => '🧠 Memory',
            'cpu_load' => '⚡ CPU Load',
            'queue' => '📋 Queue',
            'database' => '🗄️ Database',
            'agent_errors' => '🤖 Agent Errors',
            'supervisor' => '⚙️ Supervisor',
        ];

        foreach ($labels as $key => $label) {
            if (isset($metrics[$key])) {
                $msg .= "{$label}: {$metrics[$key]}\n";
            }
        }

        return $msg;
    }

    /**
     * After gateway recovers from downtime, automatically find and reply to missed DMs.
     */
    private function autoRecoverIfNeeded(array $metrics): void
    {
        $wasDownKey = self::CACHE_PREFIX . 'wa_was_down';
        $recoveryRanKey = self::CACHE_PREFIX . 'recovery_ran';
        $gatewayStatus = $metrics['wa_gateway'] ?? '';
        $isOpen = str_starts_with($gatewayStatus, 'open');

        if (!$isOpen) {
            // Gateway is down — mark it
            Cache::put($wasDownKey, now()->toDateTimeString(), now()->addHours(2));
            return;
        }

        // Gateway is open — check if it was previously down
        $wasDown = Cache::get($wasDownKey);
        if (!$wasDown)
            return;

        // Prevent running recovery multiple times for the same downtime
        if (Cache::has($recoveryRanKey))
            return;

        // Gateway just recovered! Run missed message recovery
        Cache::put($recoveryRanKey, true, now()->addMinutes(30));
        Cache::forget($wasDownKey);

        Log::info('MonitorSystem: gateway recovered from downtime, running wa:recover');
        $this->info('🔄 Gateway recovered — running missed message recovery...');

        try {
            Artisan::call('wa:recover', ['--minutes' => 30]);
            $output = Artisan::output();
            Log::info('MonitorSystem: recovery output', ['output' => $output]);
            $this->line($output);

            // Notify admin about the recovery
            try {
                app(WhacenterService::class)->sendMessage(
                    self::ADMIN_PHONE,
                    "🔄 *Auto-Recovery Selesai*\n\n"
                        . "Gateway sempat down, sekarang sudah kembali online.\n"
                        . "Bot otomatis mencari dan membalas pesan yang terlewat selama downtime.\n\n"
                        . trim($output)
                );
            } catch (\Throwable $e) {
                // Recovery notification failed, but recovery itself was done
            }
        } catch (\Throwable $e) {
            Log::error('MonitorSystem: auto-recovery failed', ['error' => $e->getMessage()]);
        }
    }

    private function getSeverity(array $problems): string
    {
        foreach ($problems as $p) {
            if (str_contains($p['key'], 'critical') || str_contains($p['key'], 'down') || str_contains($p['key'], 'unreachable')) {
                return 'critical';
            }
        }
        return empty($problems) ? 'ok' : 'warning';
    }
}
