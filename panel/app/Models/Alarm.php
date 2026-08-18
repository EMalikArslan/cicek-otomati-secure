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
 * @property string $code
 * @property string $severity
 * @property array<array-key, mixed>|null $detail
 * @property Carbon $opened_at
 * @property int|null $acknowledged_by_id
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $acknowledgedBy
 * @property-read Machine|null $machine
 *
 * @method static Builder<static>|Alarm critical()
 * @method static Builder<static>|Alarm newModelQuery()
 * @method static Builder<static>|Alarm newQuery()
 * @method static Builder<static>|Alarm open()
 * @method static Builder<static>|Alarm query()
 * @method static Builder<static>|Alarm whereAcknowledgedAt($value)
 * @method static Builder<static>|Alarm whereAcknowledgedById($value)
 * @method static Builder<static>|Alarm whereCode($value)
 * @method static Builder<static>|Alarm whereCreatedAt($value)
 * @method static Builder<static>|Alarm whereDetail($value)
 * @method static Builder<static>|Alarm whereId($value)
 * @method static Builder<static>|Alarm whereMachineId($value)
 * @method static Builder<static>|Alarm whereOpenedAt($value)
 * @method static Builder<static>|Alarm whereResolvedAt($value)
 * @method static Builder<static>|Alarm whereSeverity($value)
 * @method static Builder<static>|Alarm whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'code', 'severity', 'detail', 'opened_at',
    'acknowledged_by_id', 'acknowledged_at', 'resolved_at',
])]
class Alarm extends Model
{
    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'opened_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }

    /** @param  Builder<static>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /** @param  Builder<static>  $query */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Ayni sorun icin acik alarm varsa yenisini acmaz - alarm yagmurunu onler.
     *
     * @param  array<string, mixed>  $detail
     */
    public static function raise(Machine $machine, string $code, string $severity, array $detail = []): self
    {
        $existing = static::query()
            ->where('machine_id', $machine->id)
            ->where('code', $code)
            ->whereNull('resolved_at')
            ->first();

        if ($existing !== null) {
            $existing->update(['detail' => $detail]);

            return $existing;
        }

        return static::create([
            'machine_id' => $machine->id,
            'code' => $code,
            'severity' => $severity,
            'detail' => $detail,
            'opened_at' => now(),
        ]);
    }

    public static function resolve(Machine $machine, string $code): void
    {
        static::query()
            ->where('machine_id', $machine->id)
            ->where('code', $code)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now()]);
    }
}
