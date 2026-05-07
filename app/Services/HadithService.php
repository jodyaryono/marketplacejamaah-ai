<?php

namespace App\Services;

class HadithService
{
    /**
     * Collection of sahih hadith about trade/commerce (perdagangan/jual beli).
     */
    private static array $hadiths = [
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Pedagang yang jujur dan terpercaya akan bersama para nabi, orang-orang shiddiq, dan para syuhada (pada hari kiamat).\"_\n\n📖 *HR. Tirmidzi no. 1209* — Hasan Shahih",
            'theme' => 'Kejujuran pedagang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Dua orang yang melakukan jual beli masing-masing memiliki hak khiyar (memilih) selama keduanya belum berpisah. Jika keduanya jujur dan menjelaskan (kondisi barang), maka jual beli mereka diberkahi. Namun jika keduanya menyembunyikan (cacat) dan berdusta, maka keberkahan jual beli mereka dihapus.\"_\n\n📖 *HR. Bukhari no. 2079 & Muslim no. 1532*",
            'theme' => 'Keberkahan jual beli',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Sesungguhnya para pedagang akan dibangkitkan pada hari kiamat sebagai orang-orang fajir (jahat), kecuali orang yang bertakwa kepada Allah, berbuat baik, dan berkata jujur.\"_\n\n📖 *HR. Tirmidzi no. 1210, Ibnu Majah no. 2146* — Hasan Shahih",
            'theme' => 'Pedagang dan hari kiamat',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Penjual dan pembeli memiliki hak khiyar (memilih) selama keduanya belum berpisah dari tempat jual beli.\"_\n\n📖 *HR. Bukhari no. 2107 & Muslim no. 1531*",
            'theme' => 'Hak khiyar dalam jual beli',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Allah merahmati seseorang yang bersikap mudah (toleran) ketika menjual, mudah ketika membeli, dan mudah ketika menagih.\"_\n\n📖 *HR. Bukhari no. 2076*",
            'theme' => 'Toleransi dalam berdagang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tidaklah seorang muslim menanam tanaman atau bercocok tanam lalu dimakan darinya oleh burung, manusia, atau binatang, melainkan menjadi sedekah baginya.\"_\n\n📖 *HR. Bukhari no. 2320 & Muslim no. 1553*",
            'theme' => 'Usaha dan sedekah',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tidak halal bagi seseorang menjual suatu barang yang dia tahu ada cacatnya, kecuali dia menjelaskan cacat tersebut.\"_\n\n📖 *HR. Ahmad no. 15978, Ibnu Majah no. 2246* — Shahih",
            'theme' => 'Larangan menyembunyikan cacat barang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Sebaik-baik penghasilan adalah penghasilan seorang pekerja, apabila dia mengerjakannya dengan ikhlas.\"_\n\n📖 *HR. Ahmad no. 8419* — Hasan",
            'theme' => 'Usaha dengan ikhlas',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Barangsiapa yang menangguhkan (hutang) orang yang kesulitan, atau membebaskannya, maka Allah akan menaunginya di bawah naungan-Nya pada hari yang tidak ada naungan kecuali naungan-Nya.\"_\n\n📖 *HR. Muslim no. 3006*",
            'theme' => 'Memberi kelonggaran hutang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Rasulullah ﷺ melarang jual beli gharar (yang mengandung ketidakjelasan).\"_\n\n📖 *HR. Muslim no. 1513*",
            'theme' => 'Larangan jual beli gharar',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Barangsiapa ingin dilapangkan rezekinya dan dipanjangkan umurnya, maka hendaklah ia menyambung tali silaturahmi.\"_\n\n📖 *HR. Bukhari no. 5986 & Muslim no. 2557*",
            'theme' => 'Silaturahmi dan rezeki',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Janganlah kalian saling hasad, janganlah saling menaikkan harga (untuk menipu orang lain), janganlah saling membenci, janganlah saling membelakangi, dan janganlah sebagian kalian menjual di atas jualan sebagian yang lain.\"_\n\n📖 *HR. Muslim no. 2564*",
            'theme' => 'Larangan persaingan tidak sehat',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tangan di atas (yang memberi) lebih baik daripada tangan di bawah (yang meminta). Mulailah dari orang yang menjadi tanggunganmu. Sebaik-baik sedekah adalah yang dikeluarkan dari kelebihan.\"_\n\n📖 *HR. Bukhari no. 1427 & Muslim no. 1034*",
            'theme' => 'Kemandirian ekonomi',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Siapa saja yang mengambil harta manusia dengan maksud untuk melunasinya, maka Allah akan tunaikan untuknya. Dan siapa saja yang mengambilnya dengan maksud untuk merusaknya (tidak membayar), maka Allah akan merusak orang itu.\"_\n\n📖 *HR. Bukhari no. 2387*",
            'theme' => 'Amanah dalam hutang piutang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Sumpah palsu itu memang melariskan dagangan, tetapi menghapus keberkahan.\"_\n\n📖 *HR. Bukhari no. 2087 & Muslim no. 1606*",
            'theme' => 'Larangan sumpah palsu dalam berdagang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tidak ada makanan yang lebih baik bagi seseorang daripada makanan yang didapat dari hasil usaha tangannya sendiri. Dan sesungguhnya Nabi Dawud AS makan dari hasil usaha tangannya sendiri.\"_\n\n📖 *HR. Bukhari no. 2072*",
            'theme' => 'Keutamaan usaha sendiri',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Muslim itu bersaudara. Tidak halal bagi seorang muslim menjual barang yang ada cacatnya kepada saudaranya kecuali ia menjelaskannya.\"_\n\n📖 *HR. Ibnu Majah no. 2246, Ahmad* — Shahih",
            'theme' => 'Kejujuran antar muslim',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Barangsiapa menempuh suatu jalan untuk mencari ilmu, maka Allah memudahkan baginya jalan menuju surga.\"_\n\n📖 *HR. Muslim no. 2699*\n\n_Termasuk ilmu tentang muamalah dan adab berdagang._",
            'theme' => 'Ilmu dalam bermuamalah',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Janganlah seseorang dari kalian menjual atas jualan saudaranya, dan janganlah seseorang meminang atas pinangan saudaranya, kecuali ia mengizinkannya.\"_\n\n📖 *HR. Bukhari no. 2139 & Muslim no. 1412*",
            'theme' => 'Etika persaingan dagang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Penundaan (pembayaran hutang) oleh orang yang mampu adalah suatu kezaliman.\"_\n\n📖 *HR. Bukhari no. 2400 & Muslim no. 1564*",
            'theme' => 'Kewajiban membayar hutang',
        ],
        [
            'text' => "Rasulullah ﷺ melaknat:\n\n_\"…pemakan riba, pemberi riba, penulis (pencatat transaksi riba), dan dua saksinya. Mereka semua sama.\"_\n\n📖 *HR. Muslim no. 1598*\n\n_Fiqih muamalah: riba haram bagi semua pihak yang terlibat, bukan hanya pemakannya._",
            'theme' => 'Fiqih muamalah: larangan riba',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Emas ditukar dengan emas, perak dengan perak, gandum dengan gandum, sya'ir dengan sya'ir, kurma dengan kurma, garam dengan garam — harus sama takarannya, sejenis, dan diserahterimakan langsung. Jika jenisnya berbeda, juallah sesuka kalian asal tunai.\"_\n\n📖 *HR. Muslim no. 1587*\n\n_Fiqih muamalah: kaidah riba fadhl & riba nasi'ah pada enam komoditas ribawi._",
            'theme' => 'Fiqih muamalah: kaidah barang ribawi',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Barangsiapa melakukan salaf (salam/pemesanan), hendaklah ia mensalafkan dengan takaran yang jelas, timbangan yang jelas, dan sampai tempo yang jelas pula.\"_\n\n📖 *HR. Bukhari no. 2240 & Muslim no. 1604*\n\n_Fiqih muamalah: rukun akad salam — barang, kuantitas, dan tempo wajib jelas._",
            'theme' => 'Fiqih muamalah: akad salam (pesanan)',
        ],
        [
            'text' => "Rasulullah ﷺ melarang:\n\n_\"…jual beli hashah (dengan melempar kerikil) dan jual beli gharar.\"_\n\n📖 *HR. Muslim no. 1513*\n\n_Fiqih muamalah: akad harus jelas objek, harga, dan serah-terimanya — gharar (spekulatif/tidak jelas) membatalkan akad._",
            'theme' => 'Fiqih muamalah: akad harus jelas',
        ],
        [
            'text' => "Rasulullah ﷺ melarang:\n\n_\"…jual beli najasy.\"_\n\n📖 *HR. Bukhari no. 2142 & Muslim no. 1516*\n\n_Fiqih muamalah: najasy yaitu menaikkan tawaran bukan untuk membeli, hanya untuk mengecoh pembeli lain — termasuk dalam penipuan._",
            'theme' => 'Fiqih muamalah: larangan najasy',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Janganlah kalian mencegat (talaqqi) barang dagangan sebelum sampai ke pasar.\"_\n\n📖 *HR. Bukhari no. 2165 & Muslim no. 1517*\n\n_Fiqih muamalah: larangan talaqqi rukban — mencegat penjual di jalan agar dibeli murah sebelum ia tahu harga pasar._",
            'theme' => 'Fiqih muamalah: larangan talaqqi rukban',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tidaklah seseorang menimbun (ihtikar) kecuali ia orang yang berdosa.\"_\n\n📖 *HR. Muslim no. 1605*\n\n_Fiqih muamalah: ihtikar (menimbun kebutuhan pokok menunggu harga naik) adalah dosa, karena menzalimi masyarakat._",
            'theme' => 'Fiqih muamalah: larangan ihtikar',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tidak boleh orang kota menjualkan untuk orang desa. Biarkanlah manusia, Allah memberi rezeki sebagian mereka dari sebagian yang lain.\"_\n\n📖 *HR. Muslim no. 1522*\n\n_Fiqih muamalah: larangan calo/makelar kota yang memanfaatkan ketidaktahuan harga penjual dari desa._",
            'theme' => 'Fiqih muamalah: larangan calo menipu',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Barangsiapa membeli makanan, janganlah ia menjualnya kembali sampai ia menerimanya dengan sempurna.\"_\n\n📖 *HR. Bukhari no. 2136 & Muslim no. 1526*\n\n_Fiqih muamalah: qabdh (serah-terima) adalah syarat sah menjual kembali barang yang dibeli._",
            'theme' => 'Fiqih muamalah: syarat qabdh',
        ],
        [
            'text' => "Dari Abu Hurairah ra., Rasulullah ﷺ melewati setumpuk makanan, lalu memasukkan tangannya ke dalamnya dan mendapati bagian bawahnya basah. Beliau bersabda:\n\n_\"Apa ini wahai penjual makanan?\" Ia menjawab: \"Terkena hujan, ya Rasulullah.\" Beliau bersabda: \"Mengapa tidak engkau letakkan di atas agar orang-orang melihatnya? Barangsiapa menipu, ia bukan golonganku.\"_\n\n📖 *HR. Muslim no. 102*\n\n_Fiqih muamalah: wajib transparan atas cacat/kondisi barang._",
            'theme' => 'Fiqih muamalah: transparansi cacat barang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tangan Allah di atas dua orang yang berserikat (syirkah) selama salah seorang dari mereka tidak berkhianat kepada yang lain. Jika salah seorang berkhianat, Dia akan mencabut (keberkahan) dari keduanya.\"_\n\n📖 *HR. Abu Dawud no. 3383, Hakim* — Shahih\n\n_Fiqih muamalah: keberkahan syirkah/kemitraan bergantung pada amanah._",
            'theme' => 'Fiqih muamalah: syirkah (kemitraan)',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Berikanlah upah pekerja sebelum kering keringatnya.\"_\n\n📖 *HR. Ibnu Majah no. 2443* — Shahih\n\n_Fiqih muamalah: akad ijarah (sewa/jasa) — pembayaran upah wajib disegerakan._",
            'theme' => 'Fiqih muamalah: akad ijarah (upah kerja)',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Seorang pemberi pinjaman dan peminjam yang menangguhkan (berbuat baik kepada yang kesulitan), keduanya berada dalam pahala yang sama hingga salah satunya terpenuhi (hutangnya).\"_\n\n📖 *HR. Ahmad, Ibnu Majah* — Shahih\n\n_Fiqih muamalah: qardh (pinjaman) adalah akad tolong-menolong, bukan komersial._",
            'theme' => 'Fiqih muamalah: qardh (pinjaman)',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Setiap pinjaman yang mendatangkan manfaat (bagi pemberi pinjaman) adalah riba.\"_\n\n📖 *Atsar shahih, Al-Baihaqi dalam Sunan Kubra*\n\n_Kaidah fiqih muamalah: qardh tidak boleh mensyaratkan tambahan/manfaat, karena itulah hakikat riba._",
            'theme' => 'Fiqih muamalah: pinjaman berbunga = riba',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Timbanglah dan lebihkanlah (sedikit untuk pembeli).\"_\n\n📖 *HR. Abu Dawud no. 3336, Tirmidzi no. 1305* — Shahih\n\n_Fiqih muamalah: adab takaran/timbangan — lebihkan, jangan kurangi._",
            'theme' => 'Fiqih muamalah: adab timbangan',
        ],
        [
            'text' => "Allah ﷻ berfirman dalam hadits qudsi:\n\n_\"Aku adalah pihak ketiga dari dua orang yang berserikat selama salah satunya tidak mengkhianati yang lain. Jika ia berkhianat, Aku keluar dari keduanya.\"_\n\n📖 *HR. Abu Dawud no. 3383* — Shahih\n\n_Fiqih muamalah: khianat dalam syirkah mengeluarkan berkah Allah dari kongsi tersebut._",
            'theme' => 'Fiqih muamalah: amanah dalam kongsi',
        ],
        [
            'text' => "Dari Jabir ra., Rasulullah ﷺ bersabda kepada seseorang yang hendak menjual buah yang belum tampak kelayakan masaknya:\n\n_\"Tidakkah engkau lihat, jika Allah menahan (mencegah) buah itu (gagal panen), dengan (dalih) apa engkau mengambil harta saudaramu?\"_\n\n📖 *HR. Muslim no. 1555*\n\n_Fiqih muamalah: larangan menjual sesuatu yang belum jelas wujud/kelayakannya (gharar)._",
            'theme' => 'Fiqih muamalah: larangan jual sebelum matang',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Barangsiapa membeli seekor kambing musharrah (yang diikat puting susunya agar terlihat banyak susunya), lalu ia memerahnya, maka ia boleh memilih: menahan kambing itu, atau mengembalikannya bersama satu sha' kurma.\"_\n\n📖 *HR. Bukhari no. 2148 & Muslim no. 1524*\n\n_Fiqih muamalah: khiyar 'aib — pembeli berhak membatalkan akad bila ada penipuan/kecacatan tersembunyi._",
            'theme' => 'Fiqih muamalah: khiyar aib',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Orang muslim terikat dengan syarat-syarat yang mereka buat, kecuali syarat yang mengharamkan yang halal atau menghalalkan yang haram.\"_\n\n📖 *HR. Tirmidzi no. 1352, Abu Dawud no. 3594* — Hasan Shahih\n\n_Kaidah fiqih muamalah: akad wajib dipenuhi selama tidak melanggar syariat._",
            'theme' => 'Fiqih muamalah: kaidah akad',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Tidak halal (menggabungkan) pinjaman dengan jual beli, tidak halal dua syarat dalam satu jual beli, tidak halal keuntungan atas sesuatu yang belum dijamin, dan tidak halal menjual sesuatu yang tidak ada padamu.\"_\n\n📖 *HR. Abu Dawud no. 3504, Tirmidzi no. 1234* — Hasan Shahih\n\n_Fiqih muamalah: empat larangan pokok — bai' ma'dum, dua akad dalam satu, jual tanpa qabdh, dan pencampuran akad qardh & bai'._",
            'theme' => 'Fiqih muamalah: empat larangan pokok',
        ],
        [
            'text' => "Rasulullah ﷺ bersabda:\n\n_\"Jangan kalian menjual sesuatu dari hasil (hewan atau tanaman) sampai jelas kelayakannya.\"_\n\n📖 *HR. Bukhari no. 2195 & Muslim no. 1534*\n\n_Fiqih muamalah: objek akad harus jelas sifat dan kualitasnya pada saat akad._",
            'theme' => 'Fiqih muamalah: kejelasan objek akad',
        ],
    ];

    /**
     * Get a random hadith, excluding any indices in $excludeIndices.
     * Accepts int (legacy: single last index) or array of ints (recent history).
     */
    public static function random(int|array|null $excludeIndices = null): array
    {
        $exclude = match (true) {
            is_int($excludeIndices)   => [$excludeIndices],
            is_array($excludeIndices) => array_map('intval', $excludeIndices),
            default                   => [],
        };

        $indices = array_values(array_diff(array_keys(self::$hadiths), $exclude));

        // Safety: if exclusion wiped out everything, fall back to full pool.
        if (empty($indices)) {
            $indices = array_keys(self::$hadiths);
        }

        $index = $indices[array_rand($indices)];

        return [
            'index' => $index,
            'theme' => self::$hadiths[$index]['theme'],
            'text' => self::$hadiths[$index]['text'],
        ];
    }

    /**
     * Format a hadith for WhatsApp group message.
     */
    public static function formatForWhatsApp(array $hadith): string
    {
        $header = "🕌 *Hadits Harian — Adab Jual Beli*\n\n";
        $footer = "\n\n─────────────────\n_🤖 Bot Marketplace Jamaah — Muamalah yang berkah dimulai dari adab yang baik_";

        return $header . $hadith['text'] . $footer;
    }

    /**
     * Total number of hadiths available.
     */
    public static function count(): int
    {
        return count(self::$hadiths);
    }
}
