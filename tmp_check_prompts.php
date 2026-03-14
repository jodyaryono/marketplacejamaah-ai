<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$keys = ['prompt_onboarding_approval', 'prompt_onboarding_chat'];
foreach ($keys as $k) {
    $val = App\Models\Setting::get($k, 'NOT_SET');
    echo "=== {$k} ===\n{$val}\n\n";
}
