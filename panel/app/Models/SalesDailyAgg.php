<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gun x goz ozeti. Hafta/ay/yil kapsamlari bu tablodan toplanir.
 *
 * @property int $id
 * @property int $machine_id
 * @property Carbon $bucket_date
 * @property int $slot_no
 * @property int $qty
 * @property int $revenue_minor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereBucketDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereRevenueMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereSlotNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SalesDailyAgg whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['machine_id', 'bucket_date', 'slot_no', 'qty', 'revenue_minor'])]
class SalesDailyAgg extends Model
{
    protected $table = 'sales_daily_agg';

    protected function casts(): array
    {
        return [
            'bucket_date' => 'date',
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
