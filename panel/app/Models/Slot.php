<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SlotState;
use Database\Factories\SlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $machine_id
 * @property int $slot_no
 * @property bool $is_enabled
 * @property int|null $product_id
 * @property string|null $product_name
 * @property int $price_minor
 * @property string|null $image_path
 * @property SlotState $state
 * @property Carbon|null $last_restock_at
 * @property int|null $last_restock_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read Collection<int, PriceChange> $priceChanges
 * @property-read int|null $price_changes_count
 * @property-read Product|null $product
 * @property-read Collection<int, SlotRestock> $restocks
 * @property-read int|null $restocks_count
 *
 * @method static \Database\Factories\SlotFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereLastRestockAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereLastRestockById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot wherePriceMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereSlotNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Slot whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'slot_no', 'is_enabled', 'product_id', 'product_name',
    'price_minor', 'image_path', 'state', 'last_restock_at', 'last_restock_by_id',
])]
class Slot extends Model
{
    /** @use HasFactory<SlotFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'slot_no' => 'integer',
            'is_enabled' => 'boolean',
            'price_minor' => 'integer',
            'state' => SlotState::class,
            'last_restock_at' => 'datetime',
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

    /** @return HasMany<SlotRestock, $this> */
    public function restocks(): HasMany
    {
        return $this->hasMany(SlotRestock::class)->latest('filled_at');
    }

    /** @return HasMany<PriceChange, $this> */
    public function priceChanges(): HasMany
    {
        return $this->hasMany(PriceChange::class)->latest('changed_at');
    }

    /** Akilli Dolum sayfasindaki "onceki dolum" karti bunu kullanir. */
    public function lastRestock(): ?SlotRestock
    {
        return $this->restocks()->first();
    }

    /** Satisa hazir mi? (enabled + dolu + fiyat tanimli) */
    public function isSellable(): bool
    {
        return $this->is_enabled
            && $this->state->isSellable()
            && $this->price_minor > 0;
    }

    public function displayName(): string
    {
        return $this->product->name
            ?? $this->product_name
            ?? 'Tanimsiz';
    }
}
