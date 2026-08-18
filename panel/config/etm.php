<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Super admin baslangic hesabi
    |--------------------------------------------------------------------------
    | Yalnizca ilk kurulumda (seeder) kullanilir. Parola tanimli degilse
    | rastgele uretilir ve bir kez ekrana yazilir - kodda sabit parola yoktur.
    */
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Para birimi
    |--------------------------------------------------------------------------
    | Veritabaninda tum tutarlar BIGINT kurus olarak tutulur; float kullanilmaz.
    */
    'currency' => [
        'code' => env('ETM_CURRENCY', 'TRY'),
        'symbol' => '₺',
        'minor_unit_divisor' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gosterim saat dilimi
    |--------------------------------------------------------------------------
    | Veritabani UTC; gosterim otomatin kendi saat dilimine gore yapilir.
    | Otomatta tanimli degilse buradaki deger kullanilir.
    */
    'display_timezone' => env('ETM_DISPLAY_TIMEZONE', 'Europe/Istanbul'),

    /*
    |--------------------------------------------------------------------------
    | Veri saklama sureleri (gun)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'raw_telemetry_days' => (int) env('ETM_TELEMETRY_RETENTION_DAYS', 90),
        'command_log_days' => (int) env('ETM_COMMAND_RETENTION_DAYS', 730),
    ],
];
