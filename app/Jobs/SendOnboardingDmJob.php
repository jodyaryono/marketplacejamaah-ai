<?php

namespace App\Jobs;

use App\Agents\MemberOnboardingAgent;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOnboardingDmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        private int $contactId
    ) {}

    public function handle(MemberOnboardingAgent $agent): void
    {
        $contact = Contact::find($this->contactId);
        if (!$contact || $contact->is_blocked) {
            return;
        }

        $agent->resendOnboarding($contact);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning("SendOnboardingDmJob failed for contact {$this->contactId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
