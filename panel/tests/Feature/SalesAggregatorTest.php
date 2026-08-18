<?php

declare(strict_types=1);

use App\Enums\SaleStatus;
use App\Models\Machine;
use App\Models\ProductDailyAgg;
use App\Models\SalesDailyAgg;
use App\Models\SalesHourlyAgg;
use App\Services\Analytics\SalesAggregator;

use function Tests\makeSale;

it('tek satisi uc ozet tablosuna da isler', function (): void {
    $machine = Machine::factory()->create();
    $sale = makeSale($machine, '2026-03-10 14:20:00', 7, 125000, 'Papatya');

    app(SalesAggregator::class)->applySale($sale);

    expect(SalesHourlyAgg::query()->where('slot_no', 7)->value('qty'))->toBe(1)
        ->and(SalesHourlyAgg::query()->where('slot_no', 7)->value('revenue_minor'))->toBe(125000)
        ->and(SalesDailyAgg::query()->where('slot_no', 7)->value('qty'))->toBe(1)
        ->and(ProductDailyAgg::query()->where('product_key', 'papatya')->value('qty'))->toBe(1)
        ->and(ProductDailyAgg::query()->where('product_key', 'papatya')->value('revenue_minor'))->toBe(125000);
});

it('ayni saatteki satislari tek kovada toplar', function (): void {
    $machine = Machine::factory()->create();
    $aggregator = app(SalesAggregator::class);

    foreach (['14:05:00', '14:35:00', '14:55:00'] as $time) {
        $aggregator->applySale(makeSale($machine, "2026-03-10 {$time}", 3, 100000, 'Gul'));
    }

    expect(SalesHourlyAgg::query()->count())->toBe(1)
        ->and(SalesHourlyAgg::query()->value('qty'))->toBe(3)
        ->and(SalesHourlyAgg::query()->value('revenue_minor'))->toBe(300000);
});

it('supheli ve kapak-acilmadi satislari ciroya katmaz', function (): void {
    $machine = Machine::factory()->create();
    $aggregator = app(SalesAggregator::class);

    $aggregator->applySale(makeSale($machine, '2026-03-10 10:00:00', 1, 50000, 'Gul', SaleStatus::Suspicious));
    $aggregator->applySale(makeSale($machine, '2026-03-10 10:00:00', 1, 50000, 'Gul', SaleStatus::LidFailed));
    $aggregator->applySale(makeSale($machine, '2026-03-10 10:00:00', 1, 50000, 'Gul'));

    expect(SalesDailyAgg::query()->sum('revenue_minor'))->toBe(50000)
        ->and(SalesDailyAgg::query()->sum('qty'))->toBe(1);
});

it('yeniden hesaplama ham veriyle bire bir tutar', function (): void {
    $machine = Machine::factory()->create();
    $expected = 0;

    foreach (range(1, 20) as $i) {
        $price = $i * 1000;
        $expected += $price;
        $day = str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT);
        makeSale($machine, "2026-03-{$day} 12:00:00", ($i % 6) + 1, $price, 'Urun '.($i % 3));
    }

    app(SalesAggregator::class)->rebuild($machine);

    expect(SalesDailyAgg::query()->sum('revenue_minor'))->toBe($expected)
        ->and(SalesHourlyAgg::query()->sum('revenue_minor'))->toBe($expected)
        ->and(ProductDailyAgg::query()->sum('revenue_minor'))->toBe($expected);
});

it('yeniden hesaplama tekrar calistirildiginda ciroyu iki katina cikarmaz', function (): void {
    $machine = Machine::factory()->create();
    makeSale($machine, '2026-03-10 12:00:00', 1, 75000, 'Papatya');

    app(SalesAggregator::class)->rebuild($machine);
    app(SalesAggregator::class)->rebuild($machine);

    expect(SalesDailyAgg::query()->sum('revenue_minor'))->toBe(75000)
        ->and(SalesDailyAgg::query()->count())->toBe(1);
});

it('serbest metin urun adlarini normalize ederek birlestirir', function (): void {
    $machine = Machine::factory()->create();
    $aggregator = app(SalesAggregator::class);

    foreach (['Tek Gül Buketi', 'tek gül buketi', '  TEK GÜL BUKETI  '] as $name) {
        $aggregator->applySale(makeSale($machine, '2026-03-10 12:00:00', 1, 10000, $name));
    }

    expect(ProductDailyAgg::query()->count())->toBe(1)
        ->and(ProductDailyAgg::query()->value('qty'))->toBe(3);
});

it('urunu bilinmeyen satisi bilinmiyor kovasina koyar', function (): void {
    $machine = Machine::factory()->create();

    app(SalesAggregator::class)->applySale(makeSale($machine, '2026-03-10 12:00:00', 1, 10000, ''));

    expect(ProductDailyAgg::query()->value('product_key'))->toBe('bilinmiyor');
});
