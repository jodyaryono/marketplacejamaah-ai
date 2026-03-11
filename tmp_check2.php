<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Check listing 22
$l = App\Models\Listing::find(22);
if ($l) {
    echo "Listing 22: title={$l->title} contact={$l->contact_number} status={$l->status}" . PHP_EOL;
    echo "  description=" . substr($l->description, 0, 100) . PHP_EOL;
} else {
    echo "Listing 22 not found" . PHP_EOL;
}

// Also check recent listings
$recent = App\Models\Listing::orderBy('id', 'desc')->take(5)->get(['id', 'title', 'contact_number', 'status', 'created_at']);
echo PHP_EOL . "Recent listings:" . PHP_EOL;
foreach ($recent as $r) {
    echo "  #{$r->id}: {$r->title} | {$r->contact_number} | {$r->status} | {$r->created_at}" . PHP_EOL;
}
