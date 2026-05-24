<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Redirect Group Ads to DM AdBuilder
    |--------------------------------------------------------------------------
    | Saat true: iklan yang diposting member (non-master) di grup WhatsApp akan
    | dihapus, dan sender otomatis di-DM bot dengan AdBuilder pre-populated.
    | Setelah user konfirmasi di DM, iklan baru tayang di grup + website via
    | BroadcastAgent (versi sudah di-polish AI).
    | Set false untuk rollback ke perilaku lama (iklan grup auto-publish apa
    | adanya).
    */
    'redirect_group_ads_to_dm' => env('REDIRECT_GROUP_ADS_TO_DM', true),
];
