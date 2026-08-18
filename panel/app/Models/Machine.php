<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MachineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 * @property int|null $owner_user_id
 * @property string|null $location_label
 * @property string|null $address
 * @property float|null $lat
 * @property float|null $lng
 * @property string $timezone
 * @property int $slot_count
 * @property string|null $firmware_version
 * @property string|null $hw_revision
 * @property string $status
 * @property int|null $temp_min
 * @property int|null $temp_max
 * @property Carbon|null $installed_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Alarm> $alarms
 * @property-read int|null $alarms_count
 * @property-read Collection<int, DeviceCommand> $commands
 * @property-read int|null $commands_count
 * @property-read DeviceCredential|null $credential
 * @property-read User|null $owner
 * @property-read Collection<int, Recommendation> $recommendations
 * @property-read int|null $recommendations_count
 * @property-read Collection<int, SlotRestock> $restocks
 * @property-read int|null $restocks_count
 * @property-read Collection<int, Sale> $sales
 * @property-read int|null $sales_count
 * @property-read Collection<int, Slot> $slots
 * @property-read int|null $slots_count
 * @property-read MachineState|null $state
 * @property-read Collection<int, MachineTelemetry> $telemetry
 * @property-read int|null $telemetry_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static Builder<static>|Machine active()
 * @method static \Database\Factories\MachineFactory factory($count = null, $state = [])
 * @method static Builder<static>|Machine newModelQuery()
 * @method static Builder<static>|Machine newQuery()
 * @method static Builder<static>|Machine onlyTrashed()
 * @method static Builder<static>|Machine query()
 * @method static Builder<static>|Machine visibleTo(\App\Models\User $user)
 * @method static Builder<static>|Machine whereAddress($value)
 * @method static Builder<static>|Machine whereCode($value)
 * @method static Builder<static>|Machine whereCreatedAt($value)
 * @method static Builder<static>|Machine whereDeletedAt($value)
 * @method static Builder<static>|Machine whereFirmwareVersion($value)
 * @method static Builder<static>|Machine whereHwRevision($value)
 * @method static Builder<static>|Machine whereId($value)
 * @method static Builder<static>|Machine whereInstalledAt($value)
 * @method static Builder<static>|Machine whereLat($value)
 * @method static Builder<static>|Machine whereLng($value)
 * @method static Builder<static>|Machine whereLocationLabel($value)
 * @method static Builder<static>|Machine whereName($value)
 * @method static Builder<static>|Machine whereNotes($value)
 * @method static Builder<static>|Machine whereOwnerUserId($value)
 * @method static Builder<static>|Machine whereSlotCount($value)
 * @method static Builder<static>|Machine whereStatus($value)
 * @method static Builder<static>|Machine whereTempMax($value)
 * @method static Builder<static>|Machine whereTempMin($value)
 * @method static Builder<static>|Machine whereTimezone($value)
 * @method static Builder<static>|Machine whereUpdatedAt($value)
 * @method static Builder<static>|Machine whereUuid($value)
 * @method static Builder<static>|Machine withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Machine withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'code', 'name', 'owner_user_id', 'location_label', 'address', 'lat', 'lng',
    'timezone', 'slot_count', 'firmware_version', 'hw_revision', 'status',
    'temp_min', 'temp_max', 'installed_at', 'notes',
])]
class Machine extends Model
{
    /** @use HasFactory<MachineFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $machine): void {
            $machine->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'slot_count' => 'integer',
            'temp_min' => 'integer',
            'temp_max' => 'integer',
            'installed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ---- Iliskiler ------------------------------------------------------

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'permissions', 'granted_by_id', 'granted_at', 'revoked_at'])
            ->withTimestamps()
            ->wherePivotNull('revoked_at');
    }

    /** @return HasOne<MachineState, $this> */
    public function state(): HasOne
    {
        return $this->hasOne(MachineState::class);
    }

    /** @return HasOne<DeviceCredential, $this> */
    public function credential(): HasOne
    {
        return $this->hasOne(DeviceCredential::class)->whereNull('revoked_at');
    }

    /** @return HasMany<Slot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class)->orderBy('slot_no');
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** @return HasMany<DeviceCommand, $this> */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /** @return HasMany<Alarm, $this> */
    public function alarms(): HasMany
    {
        return $this->hasMany(Alarm::class);
    }

    /** @return HasMany<Recommendation, $this> */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    /** @return HasMany<SlotRestock, $this> */
    public function restocks(): HasMany
    {
        return $this->hasMany(SlotRestock::class);
    }

    /** @return HasMany<MachineTelemetry, $this> */
    public function telemetry(): HasMany
    {
        return $this->hasMany(MachineTelemetry::class);
    }

    // ---- Sorgu kapsamlari -----------------------------------------------

    /**
     * Kullanicinin erisebildigi otomatlar. Her listeleme sorgusu bundan gecer;
     * IDOR'a karsi tek noktadan savunma.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->whereIn('id', $user->accessibleMachineIds());
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // ---- Yardimcilar ----------------------------------------------------

    /** MQTT topic kokU: etm/v1/m/{code} */
    public function topicPrefix(): string
    {
        return sprintf('%s/m/%s', config('mqtt-panel.topic_root', 'etm/v1'), $this->code);
    }

    public function topic(string $suffix): string
    {
        return $this->topicPrefix().'/'.ltrim($suffix, '/');
    }

    public function isOnline(): bool
    {
        return (bool) $this->state?->is_online;
    }

    public function temperatureOutOfRange(): bool
    {
        $temperature = $this->state?->temperature;

        if ($temperature === null) {
            return false;
        }

        return ($this->temp_min !== null && $temperature < $this->temp_min)
            || ($this->temp_max !== null && $temperature > $this->temp_max);
    }
}
