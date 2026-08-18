<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Topic kokU
    |--------------------------------------------------------------------------
    | Tam topic: {topic_root}/m/{machine_code}/{suffix}
    | Surum numarasi kokte tutulur; protokol degisirse eski cihazlar
    | v1'de calismaya devam ederken yenileri v2'ye gecebilir.
    */
    'topic_root' => env('MQTT_TOPIC_ROOT', 'etm/v1'),

    'topics' => [
        'status' => 'status',       // cihaz -> retained + LWT
        'telemetry' => 'tel',       // cihaz -> QoS 0
        'sale' => 'evt/sale',       // cihaz -> QoS 1
        'alarm' => 'evt/alarm',     // cihaz -> QoS 1
        'command' => 'cmd',         // panel -> QoS 1
        'ack' => 'ack',             // cihaz -> QoS 1
        'config' => 'cfg',          // panel -> QoS 1, retained
    ],

    /*
    |--------------------------------------------------------------------------
    | Komut imzalama
    |--------------------------------------------------------------------------
    | Her komut cihaza ozel gizli anahtarla HMAC-SHA256 ile imzalanir.
    | Broker ele gecirilse bile imza uretilemedigi icin sahte "gozu ac"
    | komutu gonderilemez (PLAN.md 12.1).
    */
    'signature' => [
        'algo' => 'sha256',
        'clock_skew_seconds' => 5,   // cihaz saati ile sunucu saati arasindaki tolerans
    ],

    /*
    |--------------------------------------------------------------------------
    | Hiz limitleri
    |--------------------------------------------------------------------------
    | Ele gecirilmis bir hesabin otomati bosaltmasini engeller. Asim halinde
    | komut reddedilir ve super admine `rate_limit_exceeded` alarmi gider.
    */
    'rate_limits' => [
        'open_slot' => [
            'per_minute' => env('MQTT_OPEN_SLOT_PER_MINUTE', 6),
            'per_day' => env('MQTT_OPEN_SLOT_PER_DAY', 60),
        ],
        'open_lid' => [
            'per_minute' => env('MQTT_OPEN_LID_PER_MINUTE', 10),
            'per_day' => env('MQTT_OPEN_LID_PER_DAY', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ACK bekleme suresi
    |--------------------------------------------------------------------------
    | Bu sure icinde ACK gelmezse komut `expired` isaretlenir ve kullaniciya
    | "karta ulasmadi" uyarisi gosterilir. Sessizce basarili sayilmaz.
    */
    'ack_timeout_seconds' => env('MQTT_ACK_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Cevrimdisi esigi
    |--------------------------------------------------------------------------
    | LWT gelmemis olabilir (ag bolunmesi, broker yeniden baslatmasi).
    | Son telemetriden bu yana bu sure gectiyse otomat cevrimdisi sayilir.
    */
    'offline_threshold_seconds' => env('MQTT_OFFLINE_THRESHOLD', 120),

    'telemetry_interval_seconds' => env('MQTT_TELEMETRY_INTERVAL', 30),

    /*
    |--------------------------------------------------------------------------
    | Akilli oneri olgunluk kapisi
    |--------------------------------------------------------------------------
    | Bu esikler asilmadan oneri uretilmez; panel bunun yerine ilerleme
    | cubugu gosterir (PLAN.md 10).
    */
    'recommendations' => [
        'min_sales' => env('RECOMMEND_MIN_SALES', 200),
        'min_days' => env('RECOMMEND_MIN_DAYS', 30),
        'min_observations_per_slot' => env('RECOMMEND_MIN_SLOT_OBSERVATIONS', 10),
    ],
];
