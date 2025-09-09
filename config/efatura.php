<?php

return [
    /*
    |--------------------------------------------------------------------------
    | E-Fatura API Configuration
    |--------------------------------------------------------------------------
    |
    | E-Fatura entegrasyonu için gerekli konfigürasyon ayarları
    |
    */

    'auth_wsdl' => env('EFATURA_AUTH_WSDL', 'https://api.doganedonusum.com/AuthenticationWS'),
    'invoice_wsdl' => env('EFATURA_INVOICE_WSDL', 'https://api.doganedonusum.com/EFaturaOIB'),
    'archive_wsdl' => env('EFATURA_ARCHIVE_WSDL', 'https://api.doganedonusum.com/EIArchiveWS/EFaturaArchive?wsdl'),
    
    'username' => env('EFATURA_USERNAME'),
    'password' => env('EFATURA_PASSWORD'),
    
    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    
    'sync_months' => env('EFATURA_SYNC_MONTHS', 3), // Son kaç ayın verileri çekilecek
    'timeout' => env('EFATURA_TIMEOUT', 60), // SOAP timeout süresi (saniye)
];
