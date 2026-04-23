<?php

namespace App\Support;

class SiteLocale
{
    public static function get(): string
    {
        $loc = session('site_locale', 'id');
        return in_array($loc, ['id', 'en'], true) ? $loc : 'id';
    }

    public static function is(string $code): bool
    {
        return self::get() === $code;
    }

    /** Pick between ID and EN strings. */
    public static function t(string $id, string $en): string
    {
        return self::is('en') ? $en : $id;
    }

    /**
     * Best-effort category name translation. Falls back to source string
     * when no mapping exists (so custom community categories still render).
     */
    public static function category(?string $name): ?string
    {
        if (!$name) return $name;
        if (self::is('id')) return $name;

        $map = [
            'makanan & minuman'                => 'Food & Drink',
            'makanan'                          => 'Food',
            'minuman'                          => 'Drinks',
            'pakaian'                          => 'Clothing',
            'fashion'                          => 'Fashion',
            'elektronik'                       => 'Electronics',
            'properti'                         => 'Property',
            'kendaraan'                        => 'Vehicles',
            'jasa & layanan'                   => 'Services',
            'jasa'                             => 'Services',
            'layanan'                          => 'Services',
            'buku & pendidikan'                => 'Books & Education',
            'buku'                             => 'Books',
            'pendidikan'                       => 'Education',
            'kesehatan & kecantikan'           => 'Health & Beauty',
            'kesehatan'                        => 'Health',
            'kecantikan'                       => 'Beauty',
            'hobi & olahraga'                  => 'Hobby & Sports',
            'olahraga'                         => 'Sports',
            'hobi'                             => 'Hobby',
            'hewan peliharaan'                 => 'Pets',
            'hewan'                            => 'Pets',
            'sembilan bahan pokok (sembako)'   => 'Household Essentials',
            'sembako'                          => 'Household Essentials',
            'perlengkapan rumah'               => 'Home Supplies',
            'furnitur'                         => 'Furniture',
            'mainan & bayi'                    => 'Toys & Baby',
            'bayi'                             => 'Baby',
            'lainnya'                          => 'Other',
            'lain-lain'                        => 'Other',
        ];

        $key = mb_strtolower(trim($name));
        return $map[$key] ?? $name;
    }

    /** Localized WhatsApp-seller template. %s is listing title. */
    public static function waSellerMessage(string $listingTitle): string
    {
        $tmpl = self::is('en')
            ? 'Hi, I\'m interested in the "%s" listing. Is it still available?'
            : 'Halo, saya tertarik dengan produk "%s". Apakah masih tersedia?';
        return sprintf($tmpl, $listingTitle);
    }
}
