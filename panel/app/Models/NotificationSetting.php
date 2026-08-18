<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $machine_id
 * @property NotificationEvent $event
 * @property NotificationChannel $channel
 * @property string|null $target
 * @property bool $is_enabled
 * @property array<array-key, mixed>|null $quiet_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereEvent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereQuietHours($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationSetting whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'machine_id', 'event', 'channel', 'target', 'is_enabled', 'quiet_hours'])]
class NotificationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'channel' => NotificationChannel::class,
            'is_enabled' => 'boolean',
            'quiet_hours' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Su an gonderime uygun mu? Kritik olaylar sessiz saatleri dinlemez.
     */
    public function shouldSendNow(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if ($this->event->ignoresQuietHours() || empty($this->quiet_hours)) {
            return true;
        }

        $from = $this->quiet_hours['from'] ?? null;
        $to = $this->quiet_hours['to'] ?? null;

        if ($from === null || $to === null) {
            return true;
        }

        $now = now($this->machine->timezone ?? config('app.timezone'))->format('H:i');

        // Gece yarisini asan araliklar (23:00 -> 08:00) icin ters karsilastirma.
        return $from <= $to
            ? ! ($now >= $from && $now < $to)
            : ! ($now >= $from || $now < $to);
    }
}
