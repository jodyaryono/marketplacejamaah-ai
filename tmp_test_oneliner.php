<?php
$cases = [
  "Terima kasih says Sudan join",
  "Test one",
  "Senang berada di sini",
  "Hp nokia jadul dijual murah banget hub wa 081234567890 minat serius",
  "Halo semua!\nSaya jual laptop bekas kondisi mulus\nHarga 3 juta\nMinat DM",
  "Jual baju size M kondisi baru harga 50rb minat DM",
];
foreach ($cases as $txt) {
  $t = trim($txt);
  $lines = substr_count($t, "\n") + 1;
  $words = str_word_count($t);
  $hasPrice = (bool) preg_match("/\d+\s*[kK]\b|\d+[.,]?\d*\s*(rb|ribu|jt|juta|miliar|miliyar|milyar)\b|Rp\.?\s*\d|\b\d{4,}\b/i", $t);
  $del = ($lines === 1 && $words < 12 && !$hasPrice) ? "DELETE" : "KEEP  ";
  echo "$del  lines=$lines words=$words price=".($hasPrice?"yes":"no")."  \"$t\"\n";
}
