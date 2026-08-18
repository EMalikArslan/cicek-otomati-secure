<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $machine_id
 * @property int $slot_no
 * @property int|null $product_id
 * @property string|null $product_name_snapshot
 * @property string|null $product_key
 * @property int $price_minor
 * @property SaleStatus $status
 * @property Carbon $sold_at
 * @property int|null $local_id
 * @property string|null $payment_ref
 * @property array<array-key, mixed>|null $raw
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read Product|null $product
 *
 * @method static Builder<static>|Sale between(\DateTimeInterface $from, \DateTimeInterface $to)
 * @method static \Database\Factories\SaleFactory factory($count = null, $state = [])
 * @method static Builder<static>|Sale needsAttention()
 * @method static Builder<static>|Sale newModelQuery()
 * @method static Builder<static>|Sale newQuery()
 * @method static Builder<static>|Sale query()
 * @method static Builder<static>|Sale revenueCounting()
 * @method static Builder<static>|Sale whereCreatedAt($value)
 * @method static Builder<static>|Sale whereId($value)
 * @method static Builder<static>|Sale whereLocalId($value)
 * @method static Builder<static>|Sale whereMachineId($value)
 * @method static Builder<static>|Sale wherePaymentRef($value)
 * @method static Builder<static>|Sale wherePriceMinor($value)
 * @method static Builder<static>|Sale whereProductId($value)
 * @method static Builder<static>|Sale whereProductKey($value)
 * @method static Builder<static>|Sale whereProductNameSnapshot($value)
 * @method static Builder<static>|Sale whereRaw($value)
 * @method static Builder<static>|Sale whereSlotNo($value)
 * @method static Builder<static>|Sale whereSoldAt($value)
 * @method static Builder<static>|Sale whereStatus($value)
 * @method static Builder<static>|Sale whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'slot_no', 'product_id', 'product_name_snapshot', 'product_key',
    'price_minor', 'status', 'sold_at', 'local_id', 'payment_ref', 'raw',
])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'slot_no' => 'integer',
            'price_minor' => 'integer',
            'status' => SaleStatus::class,
            'sold_at' => 'datetime',
            'local_id' => 'integer',
            'raw' => 'array',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ---- Kapsamlar ------------------------------------------------------

    /** Ciro hesaplarina giren satislar. @param  Builder<static>  $query */
    public function scopeRevenueCounting(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Success->value);
    }

    /** @param  Builder<static>  $query */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereIn('status', [
            SaleStatus::Suspicious->value,
            SaleStatus::LidFailed->value,
        ]);
    }

    /** @param  Builder<static>  $query */
    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query->whereBetween('sold_at', [$from, $to]);
    }

    // ---- Yardimcilar ----------------------------------------------------

    /**
     * Serbest metin urun adlarini agregasyonda birlestirmek icin normalize eder.
     * ("Tek Gul Buketi", "tek gül buketi " -> "tek-gul-buketi")
     */
    public static function normalizeProductKey(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'bilinmiyor';
        }

        return Str::slug(Str::lower($name)) ?: 'bilinmiyor';
    }

    public function price(): float
    {
        return $this->price_minor / 100;
    }
}
