<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Message;
use Illuminate\Console\Command;

class PruneMessages extends Command
{
    protected $signature = 'messages:prune';
    protected $description = 'Prune WAG messages older than 1 day, and DM messages for contacts who left the group';

    public function handle(): int
    {
        // 1. WAG messages: retain 1 day
        $wagCutoff = now()->subDay();
        $wagCount = Message::whereNotNull('whatsapp_group_id')
            ->where('created_at', '<', $wagCutoff)
            ->delete();

        $this->info("Pruned {$wagCount} WAG messages older than 1 day.");

        // 2. DM messages: delete when contact left group (is_registered = false)
        $leftPhones = Contact::where('is_registered', false)->pluck('phone_number');

        $dmCount = 0;
        if ($leftPhones->isNotEmpty()) {
            $dmCount = Message::whereNull('whatsapp_group_id')
                ->whereIn('sender_number', $leftPhones)
                ->delete();
        }

        $this->info("Pruned {$dmCount} DM messages for contacts who left the group.");

        return 0;
    }
}
