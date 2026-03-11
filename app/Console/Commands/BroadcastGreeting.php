<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Services\WhacenterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BroadcastGreeting extends Command
{
    protected $signature = 'broadcast:greeting {--dry-run : Show messages without sending} {--delay=8 : Delay between messages in seconds}';
    protected $description = 'Send Islamic muamalah greeting to all group members (safe broadcast with delays)';

    public function handle(WhacenterService $wa): int
    {
        $dryRun = $this->option('dry-run');
        $delay = max(5, (int) $this->option('delay'));  // minimum 5 seconds

        // Get all non-blocked contacts
        $contacts = Contact::where('is_blocked', false)
            ->whereNotNull('phone_number')
            ->get();

        $this->info("Found {$contacts->count()} contacts to greet.");
        $this->info("Delay between messages: {$delay} seconds");
        if ($dryRun) {
            $this->warn('DRY RUN — no messages will be sent.');
        }

        $sent = 0;
        $failed = 0;

        foreach ($contacts as $contact) {
            $name = $contact->name ?? 'Sahabat';
            $phone = $contact->phone_number;

            $message = $this->buildMessage($name, $contact);

            if ($dryRun) {
                $this->line('---');
                $this->line("TO: {$phone} ({$name})");
                $this->line($message);
                $sent++;
                continue;
            }

            try {
                $wa->sendMessage($phone, $message);
                $sent++;
                $this->info("[{$sent}/{$contacts->count()}] ✓ Sent to {$name} ({$phone})");
                Log::info("BroadcastGreeting: sent to {$phone}");

                // Delay to avoid WA ban — randomize slightly
                if ($sent < $contacts->count()) {
                    $actualDelay = $delay + rand(0, 3);
                    sleep($actualDelay);
                }
            } catch (\Exception $e) {
                $failed++;
                $this->error("[{$sent}/{$contacts->count()}] ✗ Failed {$name} ({$phone}): {$e->getMessage()}");
                Log::warning("BroadcastGreeting: failed to send to {$phone}", ['error' => $e->getMessage()]);
                // Still continue to next contact
                sleep($delay);
            }
        }

        $this->newLine();
        $this->info("Done! Sent: {$sent}, Failed: {$failed}");

        return Command::SUCCESS;
    }

    private function buildMessage(string $name, Contact $contact): string
    {
        $isRegistered = $contact->is_registered;

        // Personalized Islamic muamalah reminder
        $msg = "Assalamu'alaikum Warahmatullahi Wabarakatuh *{$name}* 🙏\n\n"
            . "Semoga Allah senantiasa memberkahi hari-harimu ya.\n\n"
            . 'Aku admin *Marketplace Jamaah* — komunitas jual beli sesama muslim yang InsyaAllah amanah. '
            . "Cuma mau sapa dan ingetin sedikit tentang adab bermuamalah dalam Islam:\n\n"
            . "🕌 Rasulullah ﷺ bersabda:\n"
            . "_\"Pedagang yang jujur dan amanah akan bersama para Nabi, orang-orang shiddiq, dan para syuhada.\"_\n"
            . "*(HR. Tirmidzi)*\n\n"
            . "Di Marketplace Jamaah, kita berkomitmen untuk:\n"
            . "✅ Jujur dalam deskripsi barang\n"
            . "✅ Transparan soal harga & kondisi\n"
            . "✅ Amanah dalam setiap transaksi\n"
            . "✅ Saling menghormati sesama saudara\n\n";

        if (!$isRegistered) {
            $msg .= 'Oh iya, kamu belum terdaftar resmi di komunitas kita nih. '
                . 'Boleh kenalan dong? Siapa namanya, tinggal di mana, '
                . "dan tertarik jualan, belanja, atau dua-duanya? 😊\n\n"
                . "Bales aja langsung ke chat ini ya, santai aja 🙏\n\n";
        } else {
            $msg .= 'Alhamdulillah kamu udah terdaftar di komunitas kita. '
                . "Yuk kita sama-sama jaga amanah dan terus bermuamalah dengan baik! 💪\n\n"
                . 'Kalau butuh bantuan, langsung aja chat ke sini. '
                . "Ketik *bantuan* buat lihat fitur yang tersedia ya 😊\n\n";
        }

        $msg .= "Barakallahu fiik 🤲\n"
            . '_Admin Marketplace Jamaah_';

        return $msg;
    }
}
