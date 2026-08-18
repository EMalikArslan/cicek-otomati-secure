<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $machine_id
 * @property NotificationChannel $channel
 * @property NotificationEvent $event
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string $status
 * @property string|null $target
 * @property array<array-key, mixed>|null $payload
 * @property string|null $error
 * @property int|null $cost_minor
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereCostMinor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereDeliveredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereEvent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog wherePayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereProviderMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereTarget($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|NotificationLog whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'user_id', 'machine_id', 'channel', 'event', 'provider', 'provider_message_id',
    'status', 'target', 'payload', 'error', 'cost_minor', 'sent_at', 'delivered_at',
])]
class NotificationLog extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'event' => NotificationEvent::class,
            'payload' => 'array',
            'cost_minor' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * Hedef adresi log'a maskeli yazar - KVKK gerekcesiyle ham telefon/e-posta
     * gonderim kayitlarinda tutulmaz.
     */
    public static function maskTarget(string $target): string
    {
        if (str_contains($target, '@')) {
            [$local, $domain] = explode('@', $target, 2);

            return Str::substr($local, 0, 2).str_repeat('*', max(1, mb_strlen($local) - 2)).'@'.$domain;
        }

        $digits = preg_replace('/\D/', '', $target) ?? '';

        return mb_strlen($digits) > 4
            ? str_repeat('*', mb_strlen($digits) - 4).Str::substr($digits, -4)
            : '****';
    }
}
