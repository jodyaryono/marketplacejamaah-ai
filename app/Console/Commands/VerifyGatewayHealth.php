<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VerifyGatewayHealth extends Command
{
    protected $signature = 'gateway:verify {--fix : Attempt to restart gateway if unhealthy} {--quiet-ok : Suppress output if everything is OK}';
    protected $description = 'Verify the WA gateway has all required event listeners and is functioning correctly';

    private array $requiredListeners = [
        'group_join',
        'group_leave',
        'group_membership_request',
    ];

    public function handle(): int
    {
        $baseUrl = rtrim(config('services.wa_gateway.url'), '/');
        $token = config('services.wa_gateway.token');
        $phoneId = config('services.wa_gateway.phone_id');
        $quietOk = $this->option('quiet-ok');

        // The config URL already includes /api, so use it directly
        // Step 1: Check gateway is reachable
        try {
            $response = Http::timeout(10)->get("{$baseUrl}/status", [
                'token' => $token,
                'phone_id' => $phoneId,
            ]);

            if (!$response->successful()) {
                $this->error("Gateway returned HTTP {$response->status()}");
                Log::critical('gateway:verify - Gateway unreachable', ['status' => $response->status()]);
                return $this->tryFix('Gateway returned error status');
            }

            $data = $response->json();
            $sessions = $data['sessions'] ?? [];
            $mainSession = $sessions[$phoneId] ?? null;

            if (!$mainSession || ($mainSession['status'] ?? '') !== 'open') {
                $this->error("Main session {$phoneId} is not open");
                Log::critical('gateway:verify - Main WA session not open', ['sessions' => $sessions]);
                return $this->tryFix('Main WA session not open');
            }

            if (!$quietOk) {
                $this->info("✓ Gateway reachable, session {$phoneId} is open");
            }
        } catch (\Exception $e) {
            $this->error("Cannot reach gateway: {$e->getMessage()}");
            Log::critical('gateway:verify - Gateway unreachable', ['error' => $e->getMessage()]);
            return $this->tryFix('Gateway unreachable');
        }

        // Step 2: Verify event listeners exist in the source code
        $gatewayFile = '/var/www/integrasi-wa.jodyaryono.id/index.js';
        if (file_exists($gatewayFile)) {
            $source = file_get_contents($gatewayFile);
            $missing = [];

            foreach ($this->requiredListeners as $listener) {
                // Accept both whatsapp-web.js style client.on('event') and
                // Baileys/other styles using sock.ev.on or ev.on
                $found = strpos($source, "client.on('{$listener}'") !== false
                    || strpos($source, "ev.on('{$listener}'") !== false
                    || strpos($source, "sock.ev.on('{$listener}'") !== false;
                if (!$found) {
                    $missing[] = $listener;
                }
            }

            if (!empty($missing)) {
                $missingStr = implode(', ', $missing);
                $this->error("CRITICAL: Missing event listeners in gateway: {$missingStr}");
                $this->error("This means group join/leave/membership events are NOT being forwarded!");
                Log::critical('gateway:verify - Missing event listeners', ['missing' => $missing]);

                // Send alert to admin via WA
                $this->alertAdmin("⚠️ *GATEWAY ALERT*\n\nMissing event listeners: {$missingStr}\n\nGroup onboarding is BROKEN until this is fixed.\n\nRun: deploy-gateway.sh --check");

                return Command::FAILURE;
            }

            if (!$quietOk) {
                $this->info("✓ All required event listeners present (group_join, group_leave, group_membership_request)");
            }
        } else {
            if (!$quietOk) {
                $this->warn("Cannot check source file (not on same server)");
            }
        }

        // Step 3: Check recent webhook activity (verify events flowing)
        $recentGroupEvent = \App\Models\AgentLog::where('agent_name', 'like', '%group%')
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();

        if (!$quietOk) {
            if ($recentGroupEvent) {
                $this->info("✓ Group events received in last 24h");
            } else {
                $this->warn("⚠ No group events in last 24h (may be normal if no one joined/left)");
            }
        }

        if (!$quietOk) {
            $this->newLine();
            $this->info("Gateway health: OK");
        }

        return Command::SUCCESS;
    }

    private function tryFix(string $reason): int
    {
        if (!$this->option('fix')) {
            $this->warn("Use --fix to attempt automatic restart");
            return Command::FAILURE;
        }

        $this->warn("Attempting gateway restart...");
        $output = shell_exec('supervisorctl restart integrasi-wa 2>&1');
        $this->line($output ?? '');

        sleep(10);

        $output = shell_exec('supervisorctl status integrasi-wa 2>&1');
        if (str_contains($output ?? '', 'RUNNING')) {
            $this->info("Gateway restarted successfully");
            $this->alertAdmin("⚠️ *GATEWAY AUTO-RESTART*\n\nReason: {$reason}\n\nGateway was restarted automatically and is now running.");
            return Command::SUCCESS;
        }

        $this->error("Gateway restart failed!");
        $this->alertAdmin("🚨 *GATEWAY DOWN*\n\nReason: {$reason}\n\nAutomatic restart FAILED. Manual intervention required!");
        return Command::FAILURE;
    }

    private function alertAdmin(string $message): void
    {
        try {
            $adminPhone = config('services.wa_gateway.admin_phone', '6285719195627');
            app(\App\Services\WhacenterService::class)->sendMessage($adminPhone, $message);
        } catch (\Exception $e) {
            Log::error('gateway:verify - Failed to send admin alert', ['error' => $e->getMessage()]);
        }
    }
}
