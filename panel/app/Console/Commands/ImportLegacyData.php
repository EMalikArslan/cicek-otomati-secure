<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SaleStatus;
use App\Enums\SlotState;
use App\Models\Machine;
use App\Models\Sale;
use App\Models\Slot;
use App\Models\SlotRestock;
use App\Services\Analytics\SalesAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PDO;

/**
 * Streamlit/Raspberry Pi donemindeki veriyi yeni semaya tasir.
 *
 * Tekrar calistirilabilir (idempotent): satislar `(machine_id, local_id)`
 * tekilligiyle, gozler `(machine_id, slot_no)` tekilligiyle korunur. Ayni
 * komutu iki kez calistirmak cift kayit uretmez.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'etm:import-legacy
        {--path= : Eski proje klasoru (slots.json ve satislar.db burada aranir)}
        {--code=ETM_001 : Otomat kodu}
        {--name=ETM 7/24 Yozgat : Otomat adi}';

    protected $description = 'slots.json ve satislar.db icerigini panel veritabanina aktarir';

    public function handle(SalesAggregator $aggregator): int
    {
        $path = rtrim((string) ($this->option('path') ?: base_path('..')), '/');
        $slotsFile = $path.'/slots.json';
        $salesFile = $path.'/satislar.db';

        if (! is_file($slotsFile) && ! is_file($salesFile)) {
            $this->error("Kaynak bulunamadi: {$path} (slots.json / satislar.db)");

            return self::FAILURE;
        }

        $machine = $this->resolveMachine();
        $this->info("Otomat: {$machine->code} (#{$machine->id})");

        $slotCount = is_file($slotsFile) ? $this->importSlots($machine, $slotsFile) : 0;
        $saleCount = is_file($salesFile) ? $this->importSales($machine, $salesFile) : 0;

        if ($saleCount > 0) {
            $this->info('Ozet tablolari yeniden hesaplaniyor...');
            $aggregator->rebuild($machine->refresh());
        }

        $this->newLine();
        $this->info("Tamamlandi: {$slotCount} goz, {$saleCount} yeni satis.");
        $this->comment('Not: Firebase RTDB gecmisi ayrica `etm:import-firebase` ile aktarilir.');

        return self::SUCCESS;
    }

    private function resolveMachine(): Machine
    {
        return Machine::firstOrCreate(
            ['code' => (string) $this->option('code')],
            [
                'name' => (string) $this->option('name'),
                'timezone' => 'Europe/Istanbul',
                'slot_count' => 24,
                'status' => 'active',
                'installed_at' => now(),
            ],
        );
    }

    private function importSlots(Machine $machine, string $file): int
    {
        /** @var array{slots?: array<string, array<string, mixed>>} $data */
        $data = json_decode((string) file_get_contents($file), true) ?: [];
        $slots = $data['slots'] ?? [];

        if ($slots === []) {
            $this->warn('slots.json icinde goz bulunamadi.');

            return 0;
        }

        // Eski veride 24'un uzerinde goz numarasi gorulebiliyor (test kayitlari).
        // Otomatin gercek kapasitesini veriye gore genisletiyoruz ki
        // yabanci anahtar/gorunum tutarsizligi olusmasin.
        $maxSlotNo = max(array_map('intval', array_keys($slots)));

        if ($maxSlotNo > $machine->slot_count) {
            $this->warn("Veride {$maxSlotNo} numarali goz var; slot_count {$maxSlotNo} olarak guncellendi (PROTOKOL.md 1..24 diyor, kontrol edin).");
            $machine->update(['slot_count' => $maxSlotNo]);
        }

        $imported = 0;

        foreach ($slots as $slotNo => $slot) {
            $slotNo = (int) $slotNo;

            if ($slotNo < 1) {
                continue;
            }

            $priceMinor = $this->toMinor($slot['price'] ?? 0);
            $productName = $this->cleanString($slot['product_name'] ?? null);
            $imageUrl = $this->cleanString($slot['image_url'] ?? null);
            $lastRestock = $this->parseDate($slot['last_restock'] ?? null);

            $record = Slot::updateOrCreate(
                ['machine_id' => $machine->id, 'slot_no' => $slotNo],
                [
                    'is_enabled' => (bool) ($slot['enabled'] ?? false),
                    'product_name' => $productName,
                    'price_minor' => $priceMinor,
                    'image_path' => $imageUrl,
                    'state' => $productName !== null ? SlotState::Full : SlotState::Empty,
                    'last_restock_at' => $lastRestock,
                ],
            );

            // Ilk dolum kaydi: "onceki dolum" karti bos kalmasin.
            if ($productName !== null && $lastRestock !== null && $record->restocks()->count() === 0) {
                SlotRestock::create([
                    'machine_id' => $machine->id,
                    'slot_id' => $record->id,
                    'slot_no' => $slotNo,
                    'product_name' => $productName,
                    'price_minor' => $priceMinor,
                    'image_path' => $imageUrl,
                    'filled_at' => $lastRestock,
                    'source' => 'migration',
                ]);
            }

            $imported++;
        }

        return $imported;
    }

    private function importSales(Machine $machine, string $file): int
    {
        $pdo = new PDO('sqlite:'.$file, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $rows = $pdo->query('SELECT id, tarih, kutu_no, fiyat, durum, urun FROM satislar ORDER BY id')
            ->fetchAll(PDO::FETCH_ASSOC);

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $soldAt = $this->parseDate($row['tarih'] ?? null);

            if ($soldAt === null) {
                $skipped++;

                continue;
            }

            $productName = $this->cleanString($row['urun'] ?? null);
            $productName = $productName === 'Bilinmiyor' ? null : $productName;

            $sale = Sale::firstOrNew([
                'machine_id' => $machine->id,
                'local_id' => (int) $row['id'],
            ]);

            if ($sale->exists) {
                $skipped++;

                continue;
            }

            $sale->fill([
                'slot_no' => (int) $row['kutu_no'],
                'product_name_snapshot' => $productName,
                'product_key' => Sale::normalizeProductKey($productName),
                'price_minor' => $this->toMinor($row['fiyat'] ?? 0),
                'status' => $this->mapStatus((string) ($row['durum'] ?? '')),
                'sold_at' => $soldAt,
                'raw' => ['source' => 'satislar.db'],
            ])->save();

            $imported++;
        }

        if ($skipped > 0) {
            $this->line("  {$skipped} satis atlandi (zaten mevcut veya tarihi okunamadi).");
        }

        return $imported;
    }

    /** Eski veride fiyatlar TL cinsinden float; yeni semada kurus (BIGINT). */
    private function toMinor(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function mapStatus(string $durum): SaleStatus
    {
        return match (Str::lower($durum)) {
            'basarili', 'başarılı' => SaleStatus::Success,
            'supheli', 'şüpheli' => SaleStatus::Suspicious,
            'kapak_acilmadi' => SaleStatus::LidFailed,
            'iade', 'refunded' => SaleStatus::Refunded,
            default => SaleStatus::Success,
        };
    }

    private function parseDate(mixed $value): ?Carbon
    {
        $value = $this->cleanString($value);

        if ($value === null) {
            return null;
        }

        try {
            // Eski kayitlar Europe/Istanbul yerel saatinde; veritabaninda UTC tutulur.
            return Carbon::parse($value, 'Europe/Istanbul')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
