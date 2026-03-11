#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute="
\$r = \App\Models\Listing::with(['contact','category'])
    ->where('status','active')
    ->whereHas('category', fn(\$c) => \$c->where('name','ilike','%Makanan%'))
    ->latest('source_date')
    ->limit(5)
    ->get();
echo 'Count: '.\$r->count().PHP_EOL;
foreach(\$r as \$l) { echo \$l->id.' '.\$l->title.PHP_EOL; }
"
