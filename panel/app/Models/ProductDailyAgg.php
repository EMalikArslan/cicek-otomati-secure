<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Gun x urun cinsi ozeti. "Hangi cicek ne kadar satiyor" pasta grafiginin kaynagi.
 *
 * @property int $id
 * @property int $machine_id
 * @property Carbon $bucket_date
 * @property string $product_key
 * @property int|null $product_id
 * @property int $qty
 * @property int $revenue_minor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read Product|null $product
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereBucketDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereProductKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereRevenueMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductDailyAgg whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['machine_id', 'bucket_date', 'product_key', 'product_id', 'qty', 'revenue_minor'])]
class ProductDailyAgg extends Model
{
    protected $table = 'product_daily_agg';

    protected function casts(): array
    {
        return [
            'bucket_date' => 'date',
            'qty' => 'integer',
            'revenue_minor' => 'integer',
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
}
