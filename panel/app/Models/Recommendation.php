<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $machine_id
 * @property int|null $slot_no
 * @property string $type
 * @property string $title
 * @property string $body
 * @property array<array-key, mixed>|null $evidence
 * @property float|null $confidence
 * @property int $data_points
 * @property Carbon $generated_at
 * @property Carbon|null $dismissed_at
 * @property int|null $dismissed_by_id
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 *
 * @method static Builder<static>|Recommendation active()
 * @method static Builder<static>|Recommendation newModelQuery()
 * @method static Builder<static>|Recommendation newQuery()
 * @method static Builder<static>|Recommendation query()
 * @method static Builder<static>|Recommendation whereAppliedAt($value)
 * @method static Builder<static>|Recommendation whereBody($value)
 * @method static Builder<static>|Recommendation whereConfidence($value)
 * @method static Builder<static>|Recommendation whereCreatedAt($value)
 * @method static Builder<static>|Recommendation whereDataPoints($value)
 * @method static Builder<static>|Recommendation whereDismissedAt($value)
 * @method static Builder<static>|Recommendation whereDismissedById($value)
 * @method static Builder<static>|Recommendation whereEvidence($value)
 * @method static Builder<static>|Recommendation whereGeneratedAt($value)
 * @method static Builder<static>|Recommendation whereId($value)
 * @method static Builder<static>|Recommendation whereMachineId($value)
 * @method static Builder<static>|Recommendation whereSlotNo($value)
 * @method static Builder<static>|Recommendation whereTitle($value)
 * @method static Builder<static>|Recommendation whereType($value)
 * @method static Builder<static>|Recommendation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'slot_no', 'type', 'title', 'body', 'evidence',
    'confidence', 'data_points', 'generated_at', 'dismissed_at',
    'dismissed_by_id', 'applied_at',
])]
class Recommendation extends Model
{
    protected function casts(): array
    {
        return [
            'slot_no' => 'integer',
            'evidence' => 'array',
            'confidence' => 'float',
            'data_points' => 'integer',
            'generated_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('dismissed_at')->whereNull('applied_at');
    }

    public function confidenceLabel(): string
    {
        return match (true) {
            $this->confidence >= 0.8 => 'Yuksek guven',
            $this->confidence >= 0.5 => 'Orta guven',
            default => 'Dusuk guven',
        };
    }
}
