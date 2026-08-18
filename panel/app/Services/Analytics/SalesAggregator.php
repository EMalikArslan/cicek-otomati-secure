<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Enums\SaleStatus;
use App\Models\Machine;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satis ozetlerini uretir.
 *
 * Panel grafikleri ham `sales` tablosunu hic taramaz; yalnizca burada
 * uretilen ozet tablolarini okur. Boylece milyonlarca satista bile
 * grafik acilis suresi sabit kalir (PLAN.md 9).
 *
 * Iki calisma bicimi vardir:
 *  - `applySale()`  : tek satis geldiginde artimli guncelleme (sicak yol)
 *  - `rebuild()`    : bir araligi sifirdan hesaplar (gece isi + veri gocu)
 */
class SalesAggregator
{
    /** Yeni bir satisi ozetlere artimli olarak isler. */
    public function applySale(Sale $sale): void
    {
        if (! $sale->status->countsTowardRevenue()) {
            return;
        }

        $this->bump(
            machineId: $sale->machine_id,
            soldAt: $sale->sold_at,
            slotNo: $sale->slot_no,
            productKey: $sale->product_key ?? Sale::normalizeProductKey($sale->product_name_snapshot),
            productId: $sale->product_id,
            qty: 1,
            revenueMinor: $sale->price_minor,
        );
    }

    /**
     * Verilen aralikta ozetleri sifirdan hesaplar.
     *
     * Artimli guncellemede olusabilecek kaymaya (drift) karsi guvenlik agi;
     * her gece calistirilir ve veri gocunden sonra bir kez.
     */
    public function rebuild(Machine $machine, ?Carbon $from = null, ?Carbon $to = null): void
    {
        $from ??= Carbon::createFromTimestamp(0);
        $to ??= now();

        DB::transaction(function () use ($machine, $from, $to): void {
            $this->clearRange($machine, $from, $to);

            Sale::query()
                ->where('machine_id', $machine->id)
                ->where('status', SaleStatus::Success->value)
                ->whereBetween('sold_at', [$from, $to])
                ->orderBy('id')
                ->chunkById(1000, function ($sales): void {
                    foreach ($sales as $sale) {
                        $this->bump(
                            machineId: $sale->machine_id,
                            soldAt: $sale->sold_at,
                            slotNo: $sale->slot_no,
                            productKey: $sale->product_key ?? Sale::normalizeProductKey($sale->product_name_snapshot),
                            productId: $sale->product_id,
                            qty: 1,
                            revenueMinor: $sale->price_minor,
                        );
                    }
                });
        });
    }

    /** Uc ozet tablosuna da tek islemde yazar (upsert + artirma). */
    private function bump(
        int $machineId,
        Carbon $soldAt,
        int $slotNo,
        string $productKey,
        ?int $productId,
        int $qty,
        int $revenueMinor,
    ): void {
        $now = now();
        $hour = $soldAt->copy()->startOfHour();
        $date = $soldAt->copy()->startOfDay()->toDateString();

        $this->upsertCounter(
            table: 'sales_hourly_agg',
            keys: ['machine_id' => $machineId, 'bucket_hour' => $hour, 'slot_no' => $slotNo],
            qty: $qty,
            revenueMinor: $revenueMinor,
            now: $now,
        );

        $this->upsertCounter(
            table: 'sales_daily_agg',
            keys: ['machine_id' => $machineId, 'bucket_date' => $date, 'slot_no' => $slotNo],
            qty: $qty,
            revenueMinor: $revenueMinor,
            now: $now,
        );

        $this->upsertCounter(
            table: 'product_daily_agg',
            keys: ['machine_id' => $machineId, 'bucket_date' => $date, 'product_key' => $productKey],
            extra: ['product_id' => $productId],
            qty: $qty,
            revenueMinor: $revenueMinor,
            now: $now,
        );
    }

    /**
     * "Varsa artir, yoksa olustur" - yarisi engellemek icin tek SQL ifadesinde.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $extra
     */
    private function upsertCounter(
        string $table,
        array $keys,
        int $qty,
        int $revenueMinor,
        Carbon $now,
        array $extra = [],
    ): void {
        $affected = DB::table($table)
            ->where($keys)
            ->update([
                'qty' => DB::raw('qty + '.$qty),
                'revenue_minor' => DB::raw('revenue_minor + '.$revenueMinor),
                'updated_at' => $now,
            ]);

        if ($affected === 0) {
            DB::table($table)->insert([
                ...$keys,
                ...$extra,
                'qty' => $qty,
                'revenue_minor' => $revenueMinor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function clearRange(Machine $machine, Carbon $from, Carbon $to): void
    {
        DB::table('sales_hourly_agg')
            ->where('machine_id', $machine->id)
            ->whereBetween('bucket_hour', [$from->copy()->startOfHour(), $to])
            ->delete();

        DB::table('sales_daily_agg')
            ->where('machine_id', $machine->id)
            ->whereBetween('bucket_date', [$from->toDateString(), $to->toDateString()])
            ->delete();

        DB::table('product_daily_agg')
            ->where('machine_id', $machine->id)
            ->whereBetween('bucket_date', [$from->toDateString(), $to->toDateString()])
            ->delete();
    }
}
