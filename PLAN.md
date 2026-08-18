# ETM Web Panel — Laravel Mimari ve Geliştirme Planı

> Sürüm 1.0 · 19 Ağustos 2026
> Kapsam: `webpanel.md` isterlerinin Laravel tabanlı, çok otomatlı, üretim seviyesinde bir yönetim platformuna dönüştürülmesi.

---

## 1. Yönetici Özeti

Mevcut Streamlit paneli bir **prototip**: tek kaynak olarak Firebase Realtime Database kullanıyor, analiz için tüm satış geçmişini istemciye çekiyor, uzaktan kapak komutunda doğrulama/iz kaydı yok ve sayfa bazlı yetkilendirme mümkün değil. Bu yapı 1 otomatla çalışır, 10 otomatla zorlanır, 50 otomatla çöker.

Önerilen hedef mimari:

| Katman | Seçim |
|---|---|
| Uygulama | **Laravel 13** (PHP 8.4) |
| Arayüz | **Livewire 4 + Alpine.js + Tailwind CSS 4**, mobil öncelikli |
| Grafik | **ApexCharts** |
| Veritabanı | **PostgreSQL 16** (+ önceden hesaplanmış agregasyon tabloları) |
| Önbellek / Kuyruk | **Redis 7** + Laravel Horizon |
| Cihaz haberleşmesi | **MQTT 5 over TLS (EMQX)** — Firebase'in yerine |
| Tarayıcı gerçek zamanlı | **Laravel Reverb** (WebSocket) |
| Dosya/görsel | **S3 uyumlu depolama** (Cloudflare R2 veya MinIO) + medialibrary |
| Kimlik | Laravel Fortify + zorunlu TOTP 2FA (ayrıcalıklı roller) |
| Yetki | spatie/laravel-permission + otomat bazlı granüler izin matrisi |

En kritik iki karar: **Firebase RTDB → MQTT geçişi** (bölüm 5) ve **imzalı/ACK'li komut yaşam döngüsü** (bölüm 5.4). Bunlar olmadan "uzaktan kapak açma" hem güvenlik açığı hem de para kaybı riski taşımaya devam eder.

---

## 2. Mevcut Sistem Analizi

### 2.1 Envanter

| Bileşen | Dosya | Durum |
|---|---|---|
| Web panel | `app.py` (439 sat.), `Dashboard.py` (789 sat.) | Streamlit, Firebase RTDB + Storage, Firebase Auth REST |
| Kiosk yazılımı | `Raspiye gidecek dosyalar/main3.py` | CustomTkinter, SQLite, Firebase sync |
| POS köprüsü | `PosBridge.java` | Java, seri port |
| Kart protokolü | `PROTOKOL.md` | ASCII satır, seq + ACK + XOR checksum, `OPEN 1..24` |
| Yerel satış DB | `satislar.db` | `satislar(id, tarih, kutu_no, fiyat, durum, urun, synced)` — 51 kayıt |
| Göz yapılandırması | `slots.json` | `{enabled, price, product_name, image_url, last_restock}` |

### 2.2 Firebase RTDB şeması (halihazırda kullanılan)

```
machines/{DEVICE_ID}/
  info/            { online_status, last_seen, temperature, location }
  slots/{1..31}/   { enabled, price, product_name, image_url, last_restock }
  satis_hareketleri/{pushId}/ { tarih, kutu, fiyat, durum, urun }
  commands/        { open_gate: "<slotNo>" | "0" }
users/{uid}/       { email, full_name, approved, machines[] }
```

### 2.3 Tespit edilen zayıflıklar (yeni sistemde kapatılacak)

| # | Sorun | Etki | Çözüm |
|---|---|---|---|
| Z1 | `commands/open_gate` — ACK yok, idempotency yok, kim açtı belli değil | Kapak açıldı mı bilinmiyor; iz kaydı yok | İmzalı komut + ACK + `device_commands` tablosu (§5.4) |
| Z2 | RTDB kuralları zayıfsa herhangi bir istemci `open_gate` yazabilir | **Tüm gözler uzaktan boşaltılabilir** | Broker ACL + HMAC imza + sunucu tarafı yetki (§11) |
| Z3 | Heartbeat 60 sn | Cihaz ölse bile 1 dakika "online" görünür | MQTT LWT ile anlık offline (§5.2) |
| Z4 | `slots.json` içinde `admin_pass: "1234"` düz metin | Kiosk admin paneli korumasız | Hash'li PIN, sunucudan yönetim (§11) |
| Z5 | Analiz için tüm geçmiş istemciye çekiliyor (pandas) | 10k satışta panel kilitlenir | SQL agregasyon + önceden hesaplanmış özet tabloları (§9) |
| Z6 | Yetki sadece `users/{uid}/machines` listesi | Sayfa bazlı yetki verilemiyor | Otomat×kullanıcı×izin matrisi (§7) |
| Z7 | Fiyat/ürün değişiklik geçmişi yok | "Önceki dolum" ve fiyat esnekliği analizi yapılamıyor | `slot_restocks` + `price_changes` (§6) |
| Z8 | Kod boyunca `except: pass` | Hatalar sessizce yutuluyor | Yapılandırılmış loglama + Sentry (§11) |
| Z9 | Satış senkronu `(machine, local_id)` tekilliği garanti etmiyor | Yeniden gönderimde çift kayıt riski | Idempotent upsert (§6) |
| Z10 | Fiyatlar `float` | Kuruş yuvarlama hataları | `BIGINT` kuruş (§6.1) |

---

## 3. Hedef Mimari

```
┌────────────────────────────────────────────────────────────────────┐
│  TARAYICI (telefon / tablet / masaüstü)                            │
│  Livewire 4 · Alpine · Tailwind · ApexCharts · Echo(WebSocket)     │
└───────────────┬──────────────────────────────┬─────────────────────┘
                │ HTTPS                        │ WSS
┌───────────────▼──────────────────────────────▼─────────────────────┐
│  LARAVEL 13                                                        │
│  ├─ Web (Livewire)   ├─ REST API (Sanctum)  ├─ Reverb (WS sunucu)  │
│  ├─ Policies/Gates   ├─ Horizon (kuyruk)    ├─ Scheduler           │
│  └─ MQTT Bridge: `mqtt:listen` (kalıcı süreç) + Publisher jobs     │
└──────┬───────────────────┬───────────────────┬─────────────────────┘
       │                   │                   │
┌──────▼──────┐   ┌────────▼────────┐   ┌──────▼──────────┐
│ PostgreSQL  │   │ Redis (cache/   │   │ S3/R2 (görsel)  │
│    16       │   │  kuyruk/oturum) │   │                 │
└─────────────┘   └─────────────────┘   └─────────────────┘
       ▲
       │
┌──────┴──────────────────────────────────────────────────┐
│  EMQX 5 — MQTT 5 over TLS (8883)                        │
│  Cihaz başına kimlik + topic ACL + LWT                  │
└──────┬──────────────────────────────────────────────────┘
       │ TLS, QoS 1
┌──────▼──────────────────────────────────────────────────┐
│  RASPBERRY PI (otomat)                                  │
│  kiosk (main3.py) ─ etm-agent (yeni Python MQTT ajanı)  │
│                       └─ SQLite (offline tampon)        │
│  seri ─────► STM32F407 (PROTOKOL.md v1.0)               │
└─────────────────────────────────────────────────────────┘
```

---

## 4. Teknoloji Kararları ve Gerekçeleri

### 4.1 Veritabanı: PostgreSQL 16

| Aday | Değerlendirme |
|---|---|
| **PostgreSQL 16 ✅** | Analitik yükün tamamı SQL'de: pencere fonksiyonları, `generate_series` ile boşluk doldurma (satış olmayan saatler grafikte 0 görünmeli), `FILTER` ile tek geçişte çok metrik, JSONB (izin matrisi, komut payload'ı), kısmi/ifade indeksleri, aylık partition'a doğal geçiş. |
| MySQL 8 | Yeterli ama `generate_series` yok, JSON indeksleme zayıf, partition yönetimi daha kaba. |
| MongoDB | Şema esnekliği burada avantaj değil; satış/ciro verisi ilişkisel ve tutarlılık kritik. |
| Firebase RTDB (mevcut) | Sorgulanamaz. "Ekim ayında 7 numaralı gözde saat 17-19 arası kaç papatya satıldı" sorusu ancak tüm veriyi indirip yerelde hesaplayarak cevaplanır. |
| TimescaleDB | Telemetri hacmi yüksek olursa PostgreSQL üzerine eklenti olarak sonradan takılabilir — şimdilik gerekmiyor. |

**Karar:** PostgreSQL 16. Yerel geliştirmede SQLite (migration'lar taşınabilir tutulacak), üretimde PostgreSQL.

### 4.2 Cihaz haberleşmesi: MQTT (Firebase RTDB yerine)

Kullanıcının sorusu buydu; ayrıntılı karşılaştırma:

| Kriter | Firebase RTDB (mevcut) | **MQTT 5 / EMQX ✅** | REST long-poll | Laravel Reverb (WS) |
|---|---|---|---|---|
| Komut gecikmesi | ~1 sn | **< 100 ms** | 1–5 sn | < 100 ms |
| Offline tespiti | Heartbeat (60 sn) | **LWT ile anında** | Timeout | Bağlantı kopunca |
| NAT/4G arkasında | ✅ | ✅ (giden bağlantı) | ✅ | ✅ |
| Cihaz başına kimlik + topic ACL | Zayıf (kural dili) | **Güçlü, cihaz başına** | Token | Kanal yetkisi |
| Kesinti dayanıklılığı | Otomatik | **QoS 1 + persistent session** (cihaz kapalıyken kuyrukta bekler) | Yok | Yok |
| Bant genişliği (4G maliyeti) | Yüksek (JSON, sürekli sync) | **Çok düşük (binary, 2 bayt başlık)** | Orta | Orta |
| Satıcı bağımlılığı | Google'a bağlı, kullanım bazlı ücret | **Kendi sunucunda, sabit maliyet** | Yok | Yok |
| Python istemci | firebase-admin (ağır) | **paho-mqtt (hafif, ~200 KB)** | requests | websocket-client |
| Denetlenebilirlik | Sınırlı | **Broker log + rule engine** | Tam | Tam |

**Karar: EMQX 5 (MQTT 5, TLS, cihaz başına kimlik + ACL).**
Gerekçe: Otomat kontrolü *emir–onay* semantiği ister (§5.4); MQTT'nin QoS 1 + LWT + retained mesaj üçlüsü tam bu iş için tasarlanmış. Firebase'in "veritabanını dinle, değişince tepki ver" modeli komut kaybını/tekrarını gizler.
Alternatif: Tek otomatta **Mosquitto** da yeter ve daha hafiftir; EMQX'i seçmemin nedeni HTTP auth hook ile **Laravel'in kimlik kaynağı olabilmesi** (cihaz iptali tek satırlık DB güncellemesi) ve filo büyüdüğünde ölçeklenmesi.

> **Geçiş güvenliği:** Faz 1'de Firebase köprüsü korunur (Laravel hem RTDB'yi okur hem yazar), böylece saha yazılımı değişmeden panel çalışmaya başlar. MQTT'ye geçiş Faz 3'te, otomat başına kontrollü şekilde yapılır. Hiçbir aşamada satış duramaz.

### 4.3 Arayüz: Livewire 4 + Alpine + Tailwind

| Aday | Değerlendirme |
|---|---|
| **Livewire 4 + Alpine + Tailwind ✅** | Tek dil (PHP), sunucu tarafı yetki kontrolü doğrudan bileşende, dosya yükleme (akıllı dolum görselleri) hazır, Tailwind ile gerçek mobil öncelikli tasarım. |
| Filament v4 | CRUD için çok hızlı ama arayüz jenerik ve masaüstü ağırlıklı; "telefondan gözü aç" ve "sahada dolum yap" ekranları için uygun değil. *Sadece* süper admin arka ofisi için düşünülebilir; iki ayrı tasarım dili istemediğimiz için tercih edilmedi. |
| Inertia + Vue/React | Daha esnek ama ayrı bir frontend derleme/tip katmanı, küçük ekip için gereksiz yük. |

**Karar:** Livewire 4. Tüm ekranlar tek bir tasarım sistemiyle, `sm/md/lg/xl` kırılımlarında mobil öncelikli — isterdeki "telefon/tablet/pc autoscale" şartı bu şekilde karşılanır.

### 4.4 Diğer

| Konu | Karar | Gerekçe |
|---|---|---|
| Kuyruk | Redis + **Horizon** | SMS/WhatsApp/mail gönderimi, agregasyon, MQTT publish — hepsi asenkron; Horizon görünürlük sağlar. |
| Tarayıcıya canlı veri | **Laravel Reverb** | Birinci parti, Pusher maliyeti yok, Echo ile sorunsuz. |
| Görsel depolama | **Cloudflare R2** (veya MinIO) | Egress ücretsiz; kiosk her açılışta görselleri çekiyor. |
| Görsel işleme | `spatie/laravel-medialibrary` + Intervention | Otomatik boyutlandırma, WebP dönüşümü, **EXIF temizleme** (§11). |
| Yetki | `spatie/laravel-permission` + özel pivot | Global rol + otomat bazlı granüler izin (§7). |
| İz kaydı | `spatie/laravel-activitylog` | Her ayrıcalıklı işlem kaydedilir. |
| Statik analiz | **Larastan level 8** + Pest | "Zaafsız backend" isteri test ve tip güvenliğiyle desteklenir. |
| Hata izleme | Sentry (self-host seçeneği: GlitchTip) | `except: pass` kültürünün tam tersi. |
| Dağıtım | Docker Compose + Coolify (Hetzner VPS) | Tek komutla ayağa kalkan, tekrarlanabilir ortam. |

---

## 5. Cihaz Haberleşme Tasarımı

### 5.1 Topic şeması

```
etm/v1/m/{machineCode}/status     ↑ retained + LWT   {"online":true|false,"ts":...}
etm/v1/m/{machineCode}/tel        ↑ QoS0  telemetri  {ts, temp, fw, ip, uptime, stm_online}
etm/v1/m/{machineCode}/evt/sale   ↑ QoS1  satış      {local_id, slot, price_minor, product, status, ts}
etm/v1/m/{machineCode}/evt/alarm  ↑ QoS1  alarm      {code, detail, ts}
etm/v1/m/{machineCode}/cmd        ↓ QoS1  komut      {id, type, args, iat, exp, sig}
etm/v1/m/{machineCode}/ack        ↑ QoS1  onay       {id, ok, code, detail, ts}
etm/v1/m/{machineCode}/cfg        ↓ QoS1 retained    göz yapılandırması (fiyat/ürün/görsel/enabled)
```

**ACL:** Her cihaz yalnızca `etm/v1/m/{kendi-kodu}/#` altında yayın/abone olabilir. Bir otomat ele geçirilse bile diğerlerine erişemez.

### 5.2 Online/offline tespiti

Cihaz bağlanırken **Last Will & Testament** tanımlar: bağlantı koparsa broker otomatik olarak `status` topic'ine `{"online":false}` (retained) yayınlar. Böylece 60 saniyelik heartbeat gecikmesi ortadan kalkar — panel kopmayı **anında** görür. Ayrıca 30 sn'de bir `tel` mesajı sıcaklık/uptime taşır.

### 5.3 Offline dayanıklılık

- Pi tarafında satışlar önce SQLite'a yazılır (mevcut davranış korunur), MQTT'ye QoS 1 ile gönderilir, `ack` gelince `synced=1` işaretlenir.
- Cihaz kapalıyken gönderilen komutlar broker'ın **persistent session** kuyruğunda bekler; ancak `exp` alanı sayesinde geç ulaşan bir "gözü aç" komutu cihaz tarafından **reddedilir** (aşağıya bkz.).

### 5.4 Komut yaşam döngüsü (kritik)

```
Kullanıcı "Göz 7'yi aç"
   │
   ▼
[Laravel] Policy kontrolü (slots.open + bu otomat) ─ reddedilirse burada biter
   │
   ▼
[Laravel] device_commands satırı: uuid, type=open_slot, args={slot:7}, status=queued
   │        + hız limiti kontrolü (dakikada N, günde M)
   ▼
[Laravel] payload'ı kanonik JSON'a çevir → HMAC-SHA256(cihaz_secret) → sig
   │        iat=now, exp=now+30sn
   ▼
[MQTT]  etm/v1/m/ETM_001/cmd  (QoS 1)          → status=published
   │
   ▼
[Pi]  imza doğrula → exp kontrol → id daha önce görüldü mü? (son 100 id)
   │     ✗ ise: ack {ok:false, code:EXPIRED|BADSIG|DUP}
   │     ✓ ise: STM32'ye `>SEQ OPEN 7*CS` (PROTOKOL.md v1.0, 3 deneme aynı seq)
   ▼
[Pi]  ack {id, ok:true|false, code:ACK|NAK_EBUSY|NAK_ELOCK|TIMEOUT}
   ▼
[Laravel] status=acked|nacked, acked_at, error_code
   │        → Reverb ile tarayıcıya anlık bildirim (buton yeşil/kırmızı)
   │        → 15 sn içinde ack gelmezse status=expired + kullanıcıya uyarı
   ▼
[Audit] kim, ne zaman, hangi IP, hangi otomat, hangi göz — değiştirilemez kayıt
```

Bu tasarım üç şeyi garanti eder:
1. **Tekrar koruması** — aynı komut iki kez kapak açmaz (`id` nonce, Pi tarafında son 100 id saklanır; STM32'nin seq koruması ikinci savunma hattı).
2. **Sahtecilik koruması** — broker ele geçirilse bile HMAC imzası olmadan komut üretilemez.
3. **İzlenebilirlik** — "kapak açıldı mı" sorusu ACK ile, "kim açtı" sorusu audit log ile cevaplanır.

> **Bilinçli sınır:** `PROTOKOL.md`'de belirtildiği gibi donanımda kapak sensörü yok. ACK "komut karta ulaştı ve solenoid ateşlendi" demektir, "kapak fiziksel olarak açıldı" demek değildir. Panelde bu ayrım kullanıcıya açıkça gösterilecek.

---

## 6. Veri Modeli

### 6.1 Genel kurallar

- Para: **`BIGINT` kuruş** (`price_minor`), asla `float`. Para birimi `TRY`.
- Zaman: veritabanında **UTC** `timestamptz`; gösterimde otomatın `timezone` alanına göre (varsayılan `Europe/Istanbul`).
- Dış anahtarlar `ON DELETE RESTRICT`; satış/iz kayıtları asla silinmez, `soft delete` kullanılır.
- Her tabloda `created_at/updated_at`.

### 6.2 Tablolar

**Kimlik ve yetki**
| Tablo | Önemli alanlar |
|---|---|
| `users` | `name, email, phone, password, two_factor_secret, two_factor_confirmed_at, is_super_admin, status(pending/active/suspended), last_login_at, last_login_ip` |
| `roles`, `permissions`, `model_has_roles` | spatie/laravel-permission (global roller: `super_admin`, `owner`, `operator`, `viewer`) |
| `machine_user` | `machine_id, user_id, role, permissions jsonb, granted_by_id, granted_at, revoked_at` — **otomat bazlı sayfa/eylem izni** |
| `audit_logs` (activitylog) | `log_name, description, subject, causer, properties jsonb, ip, user_agent` |

**Otomatlar**
| Tablo | Önemli alanlar |
|---|---|
| `machines` | `uuid, code('ETM_001'), name, owner_user_id, location_label, address, lat, lng, timezone, slot_count, firmware_version, hw_revision, status(active/maintenance/retired), installed_at, notes` |
| `machine_states` | (1-1, hızlı okuma) `machine_id PK, is_online, last_seen_at, temperature, stm_online, ip, uptime_s, reported_lat, reported_lng, updated_at` |
| `machine_telemetry` | `machine_id, recorded_at, temperature, stm_online, extra jsonb` — zaman serisi, aylık partition, 90 gün ham + sonrası saatlik özet |
| `device_credentials` | `machine_id, mqtt_username, secret_hash, cert_fingerprint, rotated_at, revoked_at, last_auth_at` |

**Ürün ve gözler**
| Tablo | Önemli alanlar |
|---|---|
| `products` | `name, slug, category(gül/papatya/aranjman/...), default_price_minor, image_path, is_active` — "çiçek cinsi" analizinin dayanağı |
| `slots` | `machine_id, index(1..N), is_enabled, product_id, product_name, price_minor, image_path, last_restock_at, last_restock_by, state(full/empty/reserved/fault)` · `UNIQUE(machine_id,index)` |
| `slot_restocks` | `machine_id, slot_index, user_id, product_id, product_name, price_minor, image_path, prev_product_name, prev_price_minor, prev_image_path, filled_at, source(panel/kiosk)` — **"önceki dolumu göster" isteri bu tablodan** |
| `price_changes` | `slot_id, user_id, old_price_minor, new_price_minor, reason(manual/discount_5/discount_10/discount_20/campaign), changed_at` — fiyat esnekliği analizi |

**Satış**
| Tablo | Önemli alanlar |
|---|---|
| `sales` | `machine_id, slot_index, product_id, product_name_snapshot, price_minor, status(success/suspicious/lid_failed/refunded), sold_at, local_id, payment_ref, raw jsonb` · `UNIQUE(machine_id, local_id)` → **idempotent senkron** |
| `sales_hourly_agg` | `machine_id, bucket_hour, slot_index, product_id, qty, revenue_minor` |
| `sales_daily_agg` | `machine_id, bucket_date, slot_index, product_id, qty, revenue_minor` |

> Agregasyon tabloları her satışta artımlı güncellenir (job) + gece tam yeniden hesaplanır (tutarlılık ağı). Panel grafikleri **yalnızca** agregasyon tablolarını okur → 1 milyon satışta bile milisaniyelerde açılır.

**Komut ve olaylar**
| Tablo | Önemli alanlar |
|---|---|
| `device_commands` | `uuid, machine_id, user_id, type(open_slot/open_lid/set_slot_config/set_price/reboot/ping), args jsonb, status(queued/published/acked/nacked/expired/failed), published_at, acked_at, error_code, ip, user_agent` |
| `alarms` | `machine_id, code(offline/temp_high/lid_failed/payment_suspicious/stm_fault), severity, detail jsonb, opened_at, acknowledged_by, resolved_at` |

**Destek ve bildirim**
| Tablo | Önemli alanlar |
|---|---|
| `tickets` | `user_id, machine_id?, subject, body, priority(low/normal/high/urgent), status(open/in_progress/waiting_user/resolved/closed), assigned_to_id, first_response_at, resolved_at` |
| `ticket_messages` | `ticket_id, user_id, body, attachments jsonb, is_internal` |
| `notification_settings` | `user_id, machine_id?, event(sale/offline/temp_alarm/lid_failed/low_stock/daily_summary), channel(mail/sms/whatsapp/push), target, is_enabled, quiet_hours jsonb` |
| `notification_logs` | `user_id, channel, event, provider, provider_message_id, status(queued/sent/delivered/failed), error, cost_minor, sent_at` |
| `recommendations` | `machine_id, slot_index, type, title, body, evidence jsonb, confidence, data_points, generated_at, dismissed_at, applied_at` |

### 6.3 Veri göçü

1. `satislar.db` (51 kayıt) + Firebase `satis_hareketleri` → `sales` (idempotent, `local_id` ile).
2. `slots.json` + RTDB `slots` → `machines`, `slots`, ilk `slot_restocks` kaydı.
3. RTDB `users` → `users` + `machine_user` (mevcut `approved` alanı `status`'a haritalanır).
4. Görseller: Firebase Storage URL'leri indirilip R2'ye taşınır, `image_path` güncellenir.
5. Göç scripti **tekrar çalıştırılabilir** (idempotent) olacak; önce staging'de doğrulanır.

---

## 7. Yetkilendirme Modeli

İster: *"bir kullanıcı birden fazla otomata sahip olabilir; bir otomata birden fazla kişi müdahale edebilir; bu yetkileri kesinlikle süper adminden başkası sağlamayacak; her sayfa süper admin tarafından kullanıcıya açılıp kapatılabilmeli; her datayı süper admin görebilmeli."*

**Model:** Kullanıcı ⇄ Otomat çoka-çok ilişkisi, pivot üzerinde izin seti.

```
machine_user
  machine_id, user_id, role, granted_by_id, granted_at, revoked_at
  permissions jsonb:
    { "analytics.view": true,  "analytics.export": false,
      "slots.view": true,      "slots.open": true,
      "slots.price.edit": true,"lids.open": false,
      "restock.manage": true,  "settings.manage": false,
      "recommendations.view": true, "tickets.create": true }
```

Kurallar:
- İzin verme/alma yalnızca `super_admin` (Policy düzeyinde sabit; UI'da gizlemek yeterli değil).
- Her izin değişikliği `audit_logs`'a `granted_by` ile yazılır.
- `super_admin` tüm otomatları ve tüm veriyi görür (`Gate::before` ile).
- Menü, rota ve Livewire bileşeni **üç katmanda** kontrol edilir: menüde gizle → route middleware → bileşen `mount()` içinde `authorize()`. Yalnızca UI gizlemek güvenlik değildir.
- `slots.open` ve `lids.open` gibi fiziksel etki doğuran izinler için **2FA zorunlu** ve son 15 dakikada doğrulama yoksa **şifre yeniden sorulur** (step-up auth).

---

## 8. Sayfa Sayfa Fonksiyonel Tasarım

Tüm ekranlar mobil öncelikli. Kırılımlar: telefon (tek sütun, alt navigasyon), tablet (2 sütun), masaüstü (yan menü + çok sütun grid).

### 8.0 Otomat listesi (giriş sonrası ana ekran)
Kart/harita görünümü: her otomat için **isim** (kullanıcı veya süper admin tanımlı), **konum** (Leaflet + OpenStreetMap), **sıcaklık**, **online/offline rozeti** (MQTT LWT ile anlık), son satış zamanı, günlük ciro. Süper admin tüm otomatları, kullanıcı yalnızca yetkili olduklarını görür.

### 8.1 Özet ve Analiz
**Üst blok — genel:**
- KPI şeritleri: bugün/dün ciro, satış adedi, ortalama sepet, doluluk oranı, online süresi (%).
- Zaman kapsamı seçici: **Gün / Hafta / Ay / Yıl / Özel aralık** — tek tıkla tüm grafiklerin kapsamı değişir (isterdeki "kullanıcı kontrolünde").
- Çubuk grafik: seçili kapsamda ciro + adet (çift eksen), boş saatler 0 olarak doldurulur.
- Pasta/donut: ürün cinsine göre satış payı, göze göre satış payı.
- Isı haritası: **saat × gün** yoğunluğu — "hangi saatlerde satılıyor" isterinin en okunaklı hali.

**Alt blok — otomat performans derin analizi (isterdeki "detaylı kapsamlı kısım"):**
Göz bazlı tablo + grafikler:
| Metrik | Anlamı |
|---|---|
| Satış adedi / ciro | Kapsam içinde |
| **Dolum→satış süresi** (ort./medyan) | Bir göz doldurulduktan kaç saat sonra satılıyor |
| **Ciro / göz-günü** | Gözün gerçek verimliliği (boş kaldığı süre dahil) |
| **Boşta kalma süresi** | Satıldıktan sonra ne kadar boş kaldı (kayıp fırsat) |
| Ürün cinsi kırılımı | Aynı gözde hangi çiçek daha hızlı satıyor |
| Saatlik dağılım | Göz bazlı zirve saatler |
| Fiyat esnekliği | `price_changes` ile: indirim satış hızını ne kadar değiştirdi |

Dışa aktarma: CSV / XLSX (izne bağlı).

### 8.2 Göz Kontrol
- Otomatın fiziksel yerleşimine benzeyen **grid** (örn. 4×6 = 24 göz). Her kart: göz no, ürün adı + görsel, fiyat, dolu/boş durumu, `enabled` anahtarı, son dolum zamanı.
- **Aç** butonu → §5.4 komut yaşam döngüsü; buton anlık durum gösterir: *gönderiliyor → karta ulaştı (ACK) → başarısız (NAK/timeout)*.
- **Fiyat düzenleme:** doğrudan giriş + **kayar hızlı indirim menüsü** (%5 / %10 / %20 · yatay kaydırmalı chip'ler), önizleme "1780 ₺ → 1602 ₺", onayla. Her değişiklik `price_changes`'e yazılır ve MQTT `cfg` ile otomata iner.
- **Dolum sırasında yüklenen görsel** burada gösterilir (isterdeki "dolum sırasında yüklenen[i] göreceğiz").
- Toplu işlem: seçili gözleri satıştan kaldır / geri al.

### 8.3 Kapak Kontrol
Test ve hızlı dolum için sade ekran: büyük dokunmatik butonlar (telefon için ≥ 56 px), tek amaç kapak açmak.
- Güvenlik: bu sayfa `lids.open` izni + 2FA step-up ister; **hız limiti** (varsayılan 10 açılış/dakika, 100/gün) ve aşımda süper admine alarm.
- Her açılış audit log'a, gerekçe alanı opsiyonel (`test` / `dolum` / `arıza`).
- "Tümünü aç" **yok** — kasıtlı olarak; tek seferde tek göz (STM32 zaten `EBUSY` döner, ama UI'da da engellenir).

### 8.4 Akıllı Dolum
Sahada telefonla kullanılacak; adım adım sihirbaz:
1. **Göz seç** — grid'den dokun (boş gözler vurgulanır).
2. **Önceki dolumu gör** — `slot_restocks`'tan son kaydın ürün adı, görseli, fiyatı; "Aynısını doldur" kısayolu.
3. **Ürün** — otomatik tamamlamalı ürün seçimi (`products`) veya yeni ad girişi.
4. **Fiyat** — sayısal giriş + hızlı indirim chip'leri, geçen dolumla karşılaştırma.
5. **Görsel** — telefon kamerasından çek veya galeriden seç; istemcide ön küçültme, sunucuda EXIF temizleme + WebP dönüşümü.
6. **Kaydet** — `slots` güncellenir, `slot_restocks` kaydı açılır, MQTT `cfg` ile otomata iner, kiosk ekranı anında yenilenir.

Çevrimdışı dayanıklılık: zayıf sahada form taslağı `localStorage`'a yazılır, bağlantı gelince gönderilir.

### 8.5 Ayarlar ve Akıllı Öneriler
- **Bildirim tercihleri:** olay × kanal matrisi (satış / offline / sıcaklık alarmı / kapak açılmadı / düşük stok / günlük özet) × (mail / SMS / WhatsApp / push), sessiz saatler.
- **Otomat ayarları:** görünen ad, konum düzeltme (haritadan pin), sıcaklık eşikleri, çalışma saatleri, kiosk PIN'i (hash'li).
- **Akıllı öneriler:** §10.

### 8.6 Geri Bildirim ve Talep
- Kullanıcı tarafı: konu, açıklama, ek dosya, ilgili otomat, **aciliyet kayar seçici** (Düşük → Normal → Yüksek → Acil; renk ve tahmini dönüş süresi anlık gösterilir).
- Süper admin tarafı: kuyruk (aciliyet + bekleme süresine göre sıralı), atama, iç not, cevap, durum, SLA sayaçları, "ilk yanıt süresi" metriği.
- Her mesajda karşı tarafa bildirim (tercih ettiği kanaldan).

### 8.7 Süper Admin Arka Ofisi
Kullanıcı onayı/askıya alma, otomat CRUD, **otomat×kullanıcı izin matrisi** (tek ekranda checkbox tablosu), cihaz kimlik bilgisi üretme/iptal, tüm satışların ham görünümü, komut geçmişi, alarm merkezi, denetim kayıtları, sistem sağlığı (kuyruk, broker, disk).

---

## 9. Analitik Motoru

- **Yazma yolu:** satış geldiğinde → `sales`'e idempotent upsert → `UpdateSalesAggregates` job → `sales_hourly_agg` ve `sales_daily_agg` artımlı güncellenir → Reverb ile panele push.
- **Okuma yolu:** grafikler yalnızca agregasyon tablolarını sorgular. Kapsam değiştiğinde (gün→yıl) sorgu aynı tablodan farklı granülerlikte okur.
- Boş kovaların doldurulması PostgreSQL `generate_series` ile — satış olmayan saatler grafikte kaybolmaz.
- Gece 03:00'te tam yeniden hesaplama (drift'e karşı) ve 90 günden eski ham telemetri özetlenip silinir.
- Tüm ağır sorgular `EXPLAIN` ile doğrulanıp indekslenecek: `sales(machine_id, sold_at)`, `sales(machine_id, slot_index, sold_at)`, `sales_daily_agg(machine_id, bucket_date)`.

---

## 10. Akıllı Öneri Motoru

İster: *"belli bir data olgunluğuna ulaştıktan sonra, örneğin 200 satış sonra."*

**Olgunluk kapısı (yapılandırılabilir):** otomat başına ≥ **200 başarılı satış** ve ≥ **30 gün** veri ve öneri konusu göz için ≥ **10 gözlem**. Kapı aşılmadan öneri üretilmez; panelde "Öneriler için X satış daha gerekiyor (152/200)" ilerleme çubuğu gösterilir — sessiz kalmak yerine şeffaf olur.

**Sürüm 1 — kural/istatistik tabanlı (ML yok):**
| Öneri tipi | Örnek çıktı | Dayanak |
|---|---|---|
| Ürün eşleştirme | "7 numaralı gözde **Papatya** göz-günü başına 340 ₺ getiriyor (otomat ort. 180 ₺). Bu gözü papatyayla doldurmanı öneriyorum." | `sales_daily_agg` × `slot_restocks` |
| Boşta kalma | "12 numaralı göz 14 gündür boş — tahmini kayıp 2 400 ₺." | `slots.state` + ciro/göz-günü |
| Dolum zamanlaması | "Satışların %62'si Cuma–Pazar. Dolumu Perşembe akşamı yapman ciroyu ~%18 artırabilir." | saat×gün ısı haritası |
| Fiyat esnekliği | "%10 indirimde satış hızı %35 arttı, net ciro %21 arttı — indirimi sürdürebilirsin." | `price_changes` × `sales` |
| Stok uyarısı | "Bu hızla 3 göz 36 saat içinde boşalacak." | son 14 gün satış hızı |
| Anomali | "Bugünkü ciro son 4 haftanın aynı gününün %40 altında — kart okuyucu kontrol edilmeli." | z-skoru |

Her öneri **kanıtıyla** ("neden bunu öneriyorum") ve güven skoruyla gösterilir; kullanıcı *uygula / ertele / yoksay* diyebilir, geri bildirim motoru ayarlar.

**Sürüm 2 (opsiyonel, veri olgunlaşınca):** göz×ürün×zaman talep tahmini için gradient boosting; ancak §10 v1 kuralları çoğu kararı zaten kapsıyor — ML'e ihtiyaç ispatlanmadan girilmeyecek.

---

## 11. Bildirim Altyapısı (SMS / Mail / WhatsApp)

İster: *"satıcıya her satış sonrası müşterinin isteğine göre SMS/mail/WhatsApp'tan bildirim."*

| Kanal | Sağlayıcı önerisi | Not |
|---|---|---|
| Mail | Resend veya Postmark (yedek: SMTP) | Yüksek teslim oranı, webhook ile teslim takibi |
| SMS | **NetGSM** veya İletimerkezi (TR yerel) | Twilio TR'de pahalı; yerel sağlayıcı + İYS entegrasyonu şart |
| WhatsApp | **Meta WhatsApp Cloud API** | Şablon onayı gerekir (24 saat penceresi dışında yalnız onaylı şablon) |
| Push | Web Push (VAPID) | Ücretsiz, panel açıkken/kapalıyken çalışır |

**Mimari:** Laravel Notification sınıfları + kanal başına özel driver, Redis kuyruğunda, üstel geri çekilmeli yeniden deneme, başarısızlar dead-letter kuyruğuna. Her gönderim `notification_logs`'a maliyetiyle yazılır (aylık SMS bütçesi görünür olur).

**Hukuki (KVKK / İYS) — mutlaka dikkat:**
- Ticari nitelikli iletiler için **İYS (İleti Yönetim Sistemi)** kaydı ve alıcı onayı yasal zorunluluk.
- İşlem bildirimi (satış makbuzu) ticari ileti sayılmaz, ancak telefon/e-posta toplanıyorsa **aydınlatma metni + açık rıza** gerekir.
- Kiosk ekranında onay akışı ve panelde onay kaydı (`consents` tablosu, zaman damgası + IP + metin sürümü) tasarlanacak.
- Her mesajda çıkış (opt-out) yolu.

---

## 12. Güvenlik (İster: "backend siber açıdan güvenilir ve zaafsız olmalı")

### 12.1 Tehdit modeli
| Tehdit | Etki | Kontrol |
|---|---|---|
| Yetkisiz kapak açma | **Doğrudan mal kaybı** | Policy + 2FA step-up + HMAC imzalı komut + hız limiti + audit |
| Cihaz kimliği çalınması | Sahte satış/telemetri | Cihaz başına kimlik, ACL, anahtar rotasyonu, anormal davranışta otomatik iptal |
| Oturum çalma (XSS/CSRF) | Hesap ele geçirme | Laravel CSRF, Blade otomatik kaçış, katı **CSP (nonce'lu)**, `HttpOnly`+`Secure`+`SameSite` çerez |
| Kimlik bilgisi denemesi | Hesap ele geçirme | Throttle, hesap kilitleme, sızmış parola kontrolü, 2FA |
| Zararlı görsel yükleme | RCE / depolama zehirlenmesi | MIME + gerçek imza doğrulama, boyut limiti, **yeniden kodlama** (polyglot dosyaları imha eder), EXIF temizleme, ayrı domainden servis |
| SQL enjeksiyonu | Veri sızıntısı | Eloquent/parametreli sorgu, ham SQL yasak (Larastan kuralı) |
| Yetki aşımı (IDOR) | Başkasının otomatını görme | Policy + route model binding scope + `machine_user` zorunlu kontrol, testle doğrulama |
| Bağımlılık açıkları | Değişken | `composer audit` + Dependabot + CI'da engelleme |
| İç tehdit / hata | Veri kaybı | Değiştirilemez audit log, yumuşak silme, yedekten dönüş tatbikatı |

### 12.2 Uygulama kontrolleri
- Parola: Argon2id; TOTP 2FA — `super_admin` ve fiziksel kontrol izni olan herkes için **zorunlu**.
- Güvenlik başlıkları: HSTS (preload), CSP, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`.
- Oturum: Redis, kısa ömür, ayrıcalıklı işlemde yeniden doğrulama, aktif oturum listesi + uzaktan sonlandırma.
- API: Sanctum token, kapsam bazlı yetki, rota bazlı hız limiti.
- Sırlar: `.env` (repoya asla girmez — mevcut `.gitignore` doğru yapılandırılmış, `serviceAccountKey.json` takip edilmiyor ✅), üretimde şifreli secret store; anahtar rotasyon prosedürü.
- Veritabanı: en az yetkili kullanıcı, TLS bağlantı, **şifreli yedek** (günlük `pg_dump` + WAL arşivleme, offsite), üç ayda bir geri dönüş tatbikatı.
- Loglama: yapılandırılmış JSON log, PII maskeleme, Sentry; `except: pass` benzeri sessiz yutma yasak.
- CI kapıları: Larastan level 8, Pest (yetki testleri dahil), `composer audit`, Enlightn güvenlik taraması. Kırmızıysa deploy yok.
- Yayın öncesi: bağımsız sızma testi + `/security-review`.

---

## 13. Yol Haritası

| Faz | İçerik | Süre | Çıktı |
|---|---|---|---|
| **0** | Laravel iskeleti, Docker Compose, CI, tasarım sistemi, kimlik + 2FA, roller | 1 hafta | Giriş yapılabilen boş panel |
| **1** | Otomat & kullanıcı yönetimi, izin matrisi, otomat listesi (konum/sıcaklık/online), **Firebase köprüsü** | 1–2 hafta | Mevcut otomat panelde canlı görünür, saha yazılımı değişmeden |
| **2** | Satış veri modeli, veri göçü, agregasyon jobları, **Özet ve Analiz sayfası** | 1–2 hafta | Tüm grafikler ve derin göz analizi |
| **3** | EMQX kurulumu, **Pi MQTT ajanı**, komut yaşam döngüsü, **Göz Kontrol** + **Kapak Kontrol** | 1–2 hafta | ACK'li, denetlenebilir uzaktan kontrol |
| **4** | **Akıllı Dolum** (mobil), görsel işleme, dolum geçmişi | 1 hafta | Sahada telefonla dolum |
| **5** | Bildirim altyapısı (mail/SMS/WhatsApp), ayarlar, KVKK onay akışı | 1 hafta | Satış sonrası bildirim |
| **6** | Geri bildirim/talep sistemi, süper admin dönüş paneli | 1 hafta | Destek akışı |
| **7** | Akıllı öneri motoru v1 | 1 hafta | Kanıtlı öneriler |
| **8** | Güvenlik sertleştirme, yük testi, sızma testi, dokümantasyon, prod dağıtım | 1 hafta | Yayın |

Toplam ≈ **9–11 hafta**. Fazlar bağımsız teslim edilebilir; her fazın sonunda çalışan bir sürüm var.

---

## 14. Ortam ve Dağıtım

**Yerel geliştirme (bu makine):** PHP 8.4 statik derleme + Composer `~/.local/bin`'de kuruldu (root gerektirmedi). Veritabanı olarak SQLite, kuyruk/cache için `database`/`file` sürücüleri. *Not: Kalıcı çözüm için `sudo apt install php8.3-cli php8.3-{pgsql,redis,gd,intl,mbstring,xml,zip,bcmath,curl} composer postgresql redis-server` önerilir; statik ikili yalnızca iskeleyi kurmak için.*

**Üretim:** Hetzner CPX VPS + Coolify (veya Laravel Forge).
Docker Compose servisleri: `app` (PHP-FPM 8.4 + Octane), `nginx`, `postgres:16`, `redis:7`, `emqx:5`, `horizon`, `reverb`, `scheduler`, `mqtt-listener`, `minio` (R2 kullanılmazsa).
TLS: Let's Encrypt (web) + EMQX için ayrı sertifika. Yedek: günlük şifreli `pg_dump` → offsite (R2/B2).

---

## 15. Karar Bekleyen Konular

Bunlar planı bloke etmiyor (varsayımlarla ilerliyorum) ama netleşmesi işi hızlandırır:

1. **Fiyat birimi:** `slots.json`'da `1780`, `1250` görünüyor. TL mi kuruş mu? *Varsayım: TL.* Yeni sistemde kuruş olarak saklayacağım.
2. **Otomat sayısı hedefi:** 6 ay ve 2 yıl içinde kaç otomat? (Broker ve VPS boyutlandırması buna bağlı.) *Varsayım: 6 ayda < 10.*
3. **Sunucu tercihi:** Hetzner/DigitalOcean/yerli sağlayıcı? KVKK açısından veri lokasyonu tercihi var mı? *Varsayım: Hetzner (AB).*
4. **SMS/WhatsApp hesapları:** NetGSM aboneliği ve WhatsApp Business hesabı mevcut mu? İYS kaydı var mı?
5. **Pi'ye erişim:** MQTT ajanını sahadaki otomata ne zaman kurabiliriz? Uzaktan SSH/VPN erişimi var mı?
6. **Firebase geçmişi:** RTDB'deki tüm satış geçmişi göç edilecek mi, yoksa temiz başlangıç mı?
7. **Alan adı / marka:** Panel hangi alan adında yayınlanacak? Ana sayfayı yapan ekiple entegrasyon noktası (SSO gerekiyor mu, yoksa `/panel` altında ayrı giriş mi)?
8. **Çoklu dil:** Yalnızca TR mi, TR+EN mi? *Varsayım: TR, ama altyapı i18n'e hazır kurulacak.*

---

## 16. Faz 0 — Tamamlanan İş

Kod `panel/` klasöründe. Mevcut Streamlit uygulamasına dokunulmadı; geçiş süresince ikisi yan yana yaşayacak.

**Kurulan altyapı**
- Laravel 13.26 (PHP 8.4.6) · Livewire 4 · Tailwind 4 · Vite
- Fortify (2FA/passkey hazır) · spatie permission + activitylog + medialibrary
- Reverb (WebSocket) · Horizon (kuyruk) · php-mqtt/laravel-client
- Larastan (level 6) · Pest · Pint · ide-helper

**Veri modeli — 22 tablo, hepsi migrate edildi**
`machines` · `machine_states` · `machine_telemetry` · `device_credentials` · `machine_user` · `products` · `slots` · `slot_restocks` · `price_changes` · `sales` · `sales_hourly_agg` · `sales_daily_agg` · `product_daily_agg` · `device_commands` · `alarms` · `tickets` · `ticket_messages` · `notification_settings` · `notification_logs` · `consents` · `recommendations` · (+ users genişletildi)

**Yazılan uygulama katmanı**
| Bileşen | Görev |
|---|---|
| `App\Enums\MachinePermission` | 12 granüler izin + rol varsayılanları + sayfa eşlemesi + step-up işareti |
| `App\Policies\MachinePolicy` | Sayfa ve eylem bazlı yetki kuralları |
| `App\Policies\MachineUserPolicy` | Yetki dağıtımını süper admine kilitler (§7) |
| `App\Services\Access\MachineAccessManager` | Yetki ver/güncelle/kaldır + iz kaydı + izin şeması temizliği |
| `App\Services\Analytics\SalesAggregator` | Artımlı + tam yeniden hesaplama, üç özet tablosu |
| `App\Console\Commands\ImportLegacyData` | `slots.json` + `satislar.db` → yeni şema, idempotent |
| `config/mqtt-panel.php` | Topic şeması, imza, hız limitleri, ACK zaman aşımı, öneri olgunluk kapısı |

**Gerçek veriyle doğrulama**
`php artisan etm:import-legacy` çalıştırıldı: 31 göz, 51 satış aktarıldı. Üç özet tablosunun toplamı ham veriyle birebir tuttu (**35.830 TL / 51 adet**). Komut ikinci kez çalıştırıldığında hiçbir kayıt çiftlenmedi.

**Kalite kapıları — hepsi yeşil**
```
composer ci   →  pint ✅   phpstan(level 6) 0 hata ✅   pest 20/20 ✅   audit: 0 açık ✅
```
Testler yetki modelinin güvenlik davranışını kanıtlıyor: IDOR izolasyonu, sayfa bazlı izinler, "sahip bile yetki dağıtamaz" kuralı, askıya alınan hesabın erişiminin kesilmesi, bilinmeyen izin anahtarlarının reddi.

**Veriden çıkan iki uyarı**
1. `satislar.db` içinde **32 numaralı göz** kaydı var; `PROTOKOL.md` 1–24 diyor. Test kaydı olma ihtimali yüksek, ama otomatın gerçek kapasitesi teyit edilmeli.
2. Kayıtların çoğunda fiyat `0.00` ve ürün `Bilinmiyor` — bunlar saha testi kayıtları. Gerçek ciro analizi için Firebase geçmişinin de aktarılması gerekiyor.

---

## 17. Sonraki Adım

Faz 1: kimlik doğrulama arayüzü (giriş + 2FA), tasarım sistemi, otomat listesi ekranı (konum/sıcaklık/online), süper admin izin matrisi ekranı ve Firebase köprüsü.
