<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$newBody = "Halo {name}! \xF0\x9F\x91\x8B\n\nKami tadi hapus pesanmu di *{group_name}* ya \xe2\x80\x94 bukan karena kamu salah, tapi pesannya terlalu singkat dan belum kelihatan jualan apa.\n\nCoba posting ulang, bisa pakai format rapi:\n\n*[Nama barang/jasa]*\nHarga: Rp ...\nKondisi/keterangan: ...\nHub: {phone}\n\nAtau kalau mau simpel, tulis aja kalimat lengkap, contoh:\n_\"Dijual Rumah Murah 1 Milyar Rupiah di daerah Bintaro dekat akses tol, hubungi {name} di nomor ini\"_\n\nYang penting ada nama barang, harga, dan cara hubunginya ya \xF0\x9F\x98\x8A\n\nBoleh juga tambahin:\n\xF0\x9F\x93\xB7 foto produknya\n\xF0\x9F\x93\xB9 video singkat\n\xF0\x9F\x93\x8D lokasi kalau ada\n\nSekali posting langsung masuk ke website marketplace kita juga!";

$m = \App\Models\SystemMessage::where('key','group.oneliner_warning')->first();
if ($m) {
    $m->update(['body' => $newBody]);
    echo "UPDATED\n" . $m->fresh()->body . "\n";
} else {
    echo "NOT FOUND\n";
}
