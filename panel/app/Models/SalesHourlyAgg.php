<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Saat x goz ozeti. Saat-gun isi haritasinin ve gunluk grafiklerin kaynagi.
 *
 * @property int $id
 * @property int $machine_id
 * @property Carbon $bucket_hour
 * @property int $slot_no
 * @property int $qty
 * @property int $revenue_minor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereBucketHour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereRevenueMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereSlotNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesHourlyAgg whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['machine_id', 'bucket_hour', 'slot_no', 'qty', 'revenue_minor'])]
class SalesHourlyAgg extends Model
{
    protected $table = 'sales_hourly_agg';

    protected function casts(): array
    {
        return [
            'bucket_hour' => 'datetime',
            'slot_no' => 'integer',
            'qty' => 'integer',
            'revenue_minor' => 'integer',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
