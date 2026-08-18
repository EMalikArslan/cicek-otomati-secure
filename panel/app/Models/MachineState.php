<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Otomatin anlik durumu. MQTT LWT sayesinde `is_online` gecikmesiz gunceldir;
 * 60 saniyelik heartbeat beklemesi ortadan kalkar (PLAN.md 5.2).
 *
 * @property int $machine_id
 * @property bool $is_online
 * @property Carbon|null $last_seen_at
 * @property float|null $temperature
 * @property bool|null $stm_online
 * @property string|null $ip
 * @property int|null $uptime_s
 * @property float|null $reported_lat
 * @property float|null $reported_lng
 * @property string|null $agent_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereAgentVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereIsOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereLastSeenAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereReportedLat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereReportedLng($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereStmOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereTemperature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineState whereUptimeS($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'is_online', 'last_seen_at', 'temperature', 'stm_online',
    'ip', 'uptime_s', 'reported_lat', 'reported_lng', 'agent_version',
])]
class MachineState extends Model
{
    protected $primaryKey = 'machine_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'stm_online' => 'boolean',
            'last_seen_at' => 'datetime',
            'temperature' => 'float',
            'uptime_s' => 'integer',
            'reported_lat' => 'float',
            'reported_lng' => 'float',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * LWT gelmemis olabilir (broker yeniden baslamis, ag bolunmesi olmus).
     * Son telemetriden bu yana gecen sure esigi asarsa cevrimdisi sayilir.
     */
    public function isStale(int $thresholdSeconds = 120): bool
    {
        return $this->last_seen_at === null
            || $this->last_seen_at->diffInSeconds(now()) > $thresholdSeconds;
    }

    public function effectivelyOnline(): bool
    {
        return $this->is_online && ! $this->isStale();
    }
}
