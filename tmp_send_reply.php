<?php

$message = "Hehe iya kak Abie, terima kasih apresiasinya ya! 😄\n\nBetul, ini memang dirancang khusus buat grup jualan — supaya iklan anggota otomatis masuk ke website marketplace kita juga, jadi jangkauannya lebih luas.\n\nKalau ada yang mau ditanyakan atau dibantu, langsung taja tanya aja kak! 🙏";

app(\App\Services\WhacenterService::class)->sendMessage('6289666928600', $message);
echo "SENT\n";
