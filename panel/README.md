# ETM Panel

Çiçek otomatı yönetim paneli. Mimari kararlar ve yol haritası için üst klasördeki [`PLAN.md`](../PLAN.md).

Bu klasör mevcut Streamlit uygulamasından **bağımsızdır**; geçiş süresince ikisi yan yana çalışır.

## Gereksinimler

| | Sürüm |
|---|---|
| PHP | 8.4 (`pgsql`, `redis`, `gd`, `intl`, `mbstring`, `zip`, `bcmath`, `sodium`) |
| Composer | 2.x |
| Node | 20+ |
| Veritabanı | Üretim: PostgreSQL 16 · Geliştirme: SQLite yeterli |

Ubuntu'da:

```bash
sudo apt install php8.3-cli php8.3-{pgsql,redis,gd,intl,mbstring,xml,zip,bcmath,curl,sqlite3} composer
```

> Bu makinede kök yetkisi olmadığı için geliştirme sırasında `~/.local/bin/php`
> altına statik PHP 8.4 derlemesi kuruldu. Kalıcı kurulum yukarıdaki komuttur.

## Kurulum

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # süper admin oluşturur, parolayı ekrana yazar
npm install && npm run dev
php artisan serve
```

`.env` içinde ayarlanması gerekenler:

```dotenv
SUPER_ADMIN_EMAIL=...
SUPER_ADMIN_PASSWORD=          # boş bırakılırsa rastgele üretilip bir kez gösterilir
ETM_DISPLAY_TIMEZONE=Europe/Istanbul
```

## Eski veriyi aktarma

`slots.json` ve `satislar.db` içeriğini yeni şemaya taşır. Tekrar çalıştırılabilir —
aynı satış iki kez kaydedilmez.

```bash
php artisan etm:import-legacy --path=/home/malik/Desktop/Projects/Cicek_Otomati
```

## Kalite kapıları

```bash
composer ci          # pint + phpstan + pest + composer audit
composer lint        # kod stilini düzelt
composer analyse     # sadece statik analiz
php artisan test     # sadece testler
composer ide-helper  # model şeması değişince property annotation'larını yenile
```

CI kırmızıysa dağıtım yapılmaz.

## Mimari notlar

**Para.** Tüm tutarlar veritabanında `BIGINT` **kuruş** (`price_minor`). Float kullanılmaz.

**Zaman.** Veritabanı UTC (`timestamptz`); gösterim otomatın `timezone` alanına göre.

**Yetki.** Üç katmanlı: menüde gizle → route middleware → bileşende `authorize()`.
İzinler `App\Enums\MachinePermission`'da tanımlı, `machine_user.permissions` JSON'unda
otomat başına tutulur. **Yetki dağıtımı yalnızca süper adminde** — bu kural
`MachineUserPolicy` ve `MachineAccessManager` içinde iki kez uygulanır, testle korunur.

**Analitik.** Grafikler ham `sales` tablosunu asla taramaz; yalnızca
`sales_hourly_agg` / `sales_daily_agg` / `product_daily_agg` okunur.
Bu tablolar `SalesAggregator` ile artımlı güncellenir, gece tam yeniden hesaplanır.

**Cihaz komutları.** Her uzaktan müdahale önce `device_commands` tablosuna yazılır,
sonra HMAC ile imzalanıp MQTT'ye gönderilir, cihazdan ACK gelince kapanır.
"Kapak açıldı mı" ve "kim açtı" sorularının tek cevap kaynağı bu tablodur.
Ayrıntı: `PLAN.md` §5.4.

> Donanımda kapak sensörü yok (`PROTOKOL.md`). ACK "komut karta ulaştı ve solenoid
> ateşlendi" demektir; "kapak fiziksel olarak açıldı" **demez**. Arayüz bu ayrımı
> kullanıcıya açıkça göstermelidir.
