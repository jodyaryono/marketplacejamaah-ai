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
    ];

    /**
     * Get a random hadith. Optionally exclude a specific index to avoid repeats.
     */
    public static function random(?int $excludeIndex = null): array
    {
        $indices = array_keys(self::$hadiths);

        if ($excludeIndex !== null) {
            $indices = array_filter($indices, fn($i) => $i !== $excludeIndex);
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
