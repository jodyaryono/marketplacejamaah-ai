<?php

namespace App\Console\Commands;

use App\Models\AgentLog;
use Illuminate\Console\Command;

class PruneAgentLogs extends Command
{
    protected $signature = 'agent-logs:prune {--days=30 : Delete logs older than this many days}';
    protected $description = 'Delete old agent_logs records to keep the table lean';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $count = AgentLog::where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$count} agent_log records older than {$days} days.");
        return 0;
    }
}
