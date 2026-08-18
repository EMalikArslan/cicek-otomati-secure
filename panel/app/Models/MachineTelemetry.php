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
 * @property Carbon $recorded_at
 * @property float|null $temperature
 * @property bool|null $stm_online
 * @property int|null $uptime_s
 * @property array<array-key, mixed>|null $extra
 * @property-read Machine|null $machine
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry whereExtra($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry whereRecordedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry whereStmOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry whereTemperature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineTelemetry whereUptimeS($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['machine_id', 'recorded_at', 'temperature', 'stm_online', 'uptime_s', 'extra'])]
class MachineTelemetry extends Model
{
    protected $table = 'machine_telemetry';

    /** Yuksek hacimli tablo: created_at/updated_at tasimaz, `recorded_at` yeterli. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'temperature' => 'float',
            'stm_online' => 'boolean',
            'uptime_s' => 'integer',
            'extra' => 'array',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
