<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * KVKK / IYS riza kaydi.
 *
 * Ham telefon/e-posta saklanmaz: arama icin `target_hash`, gosterim icin
 * `target_masked` tutulur. `text_version` hangi aydinlatma metnine onay
 * verildigini ispatlar.
 *
 * @property int $id
 * @property int|null $machine_id
 * @property NotificationChannel $channel
 * @property string $target_hash
 * @property string $target_masked
 * @property string $text_version
 * @property string $source
 * @property Carbon $granted_at
 * @property Carbon|null $revoked_at
 * @property string|null $ip
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereGrantedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereTargetHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereTargetMasked($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereTextVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Consent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'channel', 'target_hash', 'target_masked',
    'text_version', 'source', 'granted_at', 'revoked_at', 'ip',
])]
class Consent extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    public static function hashTarget(string $target): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($target)), (string) config('app.key'));
    }

    /** Bu adres/numara icin gecerli bir riza var mi? */
    public static function existsFor(string $target, NotificationChannel $channel): bool
    {
        return static::query()
            ->where('target_hash', static::hashTarget($target))
            ->where('channel', $channel->value)
            ->whereNull('revoked_at')
            ->exists();
    }
}
