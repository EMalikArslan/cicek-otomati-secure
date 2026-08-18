<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $machine_id
 * @property int|null $slot_id
 * @property int $slot_no
 * @property int|null $user_id
 * @property int $old_price_minor
 * @property int $new_price_minor
 * @property string $reason
 * @property Carbon $changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read Slot|null $slot
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereChangedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereNewPriceMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereOldPriceMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereSlotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereSlotNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PriceChange whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'slot_id', 'slot_no', 'user_id',
    'old_price_minor', 'new_price_minor', 'reason', 'changed_at',
])]
class PriceChange extends Model
{
    protected function casts(): array
    {
        return [
            'slot_no' => 'integer',
            'old_price_minor' => 'integer',
            'new_price_minor' => 'integer',
            'changed_at' => 'datetime',
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

    /** Yuzde degisim: negatif = indirim. */
    public function percentChange(): ?float
    {
        if ($this->old_price_minor <= 0) {
            return null;
        }

        return round((($this->new_price_minor - $this->old_price_minor) / $this->old_price_minor) * 100, 1);
    }
}
