<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.wa_gateway.webhook_secret', '');

        // If no secret configured, allow all (backwards compatible during setup)
        if ($secret === '') {
            return $next($request);
        }

        $token = $request->header('X-Webhook-Secret')
            ?? $request->query('secret');

        if (!hash_equals($secret, (string) $token)) {
            return response('Unauthorized', 401);
        }

        return $next($request);
    }
}
