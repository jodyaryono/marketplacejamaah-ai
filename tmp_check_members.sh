#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$total = App\Models\Contact::count();
$registered = App\Models\Contact::where("is_registered", true)->count();
$pending = App\Models\Contact::where("onboarding_status", "pending")->count();
$blocked = App\Models\Contact::where("is_blocked", true)->count();
echo "Total contacts: $total\n";
echo "Registered: $registered\n";
echo "Pending: $pending\n";
echo "Blocked: $blocked\n";
echo "Non-blocked total: " . ($total - $blocked) . "\n";
'
