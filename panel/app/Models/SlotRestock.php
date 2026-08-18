<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Bir dolumun yasam dongusu: `filled_at` ile baslar, gozdeki urun satildiginda
 * `emptied_at` ile kapanir. Bu iki damga "dolum -> satis suresi" ve
 * "bosta kalma suresi" metriklerinin kaynagidir (PLAN.md 8.1).
 *
 * @property int $id
 * @property int $machine_id
 * @property int|null $slot_id
 * @property int $slot_no
 * @property int|null $user_id
 * @property int|null $product_id
 * @property string|null $product_name
 * @property int $price_minor
 * @property string|null $image_path
 * @property string|null $prev_product_name
 * @property int|null $prev_price_minor
 * @property string|null $prev_image_path
 * @property Carbon $filled_at
 * @property Carbon|null $emptied_at
 * @property string $source
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read Product|null $product
 * @property-read Slot|null $slot
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereEmptiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereFilledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock wherePrevImagePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock wherePrevPriceMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock wherePrevProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock wherePriceMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereProductName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereSlotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereSlotNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SlotRestock whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'slot_id', 'slot_no', 'user_id', 'product_id', 'product_name',
    'price_minor', 'image_path', 'prev_product_name', 'prev_price_minor',
    'prev_image_path', 'filled_at', 'emptied_at', 'source', 'notes',
])]
class SlotRestock extends Model
{
    protected function casts(): array
    {
        return [
            'slot_no' => 'integer',
            'price_minor' => 'integer',
            'prev_price_minor' => 'integer',
            'filled_at' => 'datetime',
            'emptied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<Slot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Dolumdan satisa kadar gecen sure (saat). Henuz satilmadiysa null. */
    public function hoursToSell(): ?float
    {
        if ($this->emptied_at === null) {
            return null;
        }

        return round($this->filled_at->diffInMinutes($this->emptied_at) / 60, 2);
    }
}
