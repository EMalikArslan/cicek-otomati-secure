<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Uzaktan mudahalenin tek kayit kaynagi (PLAN.md 5.4).
 *
 * `uuid` ayni zamanda cihaz tarafindaki tekrar korumasinin nonce'udur:
 * ayni uuid ikinci kez gelirse kapak yeniden acilmaz.
 *
 * @property int $id
 * @property string $uuid
 * @property int $machine_id
 * @property int|null $user_id
 * @property CommandType $type
 * @property array<array-key, mixed>|null $args
 * @property CommandStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $acked_at
 * @property Carbon|null $expires_at
 * @property string|null $error_code
 * @property array<array-key, mixed>|null $result
 * @property string|null $reason
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Machine|null $machine
 * @property-read User|null $user
 *
 * @method static Builder<static>|DeviceCommand newModelQuery()
 * @method static Builder<static>|DeviceCommand newQuery()
 * @method static Builder<static>|DeviceCommand pending()
 * @method static Builder<static>|DeviceCommand query()
 * @method static Builder<static>|DeviceCommand whereAckedAt($value)
 * @method static Builder<static>|DeviceCommand whereArgs($value)
 * @method static Builder<static>|DeviceCommand whereCreatedAt($value)
 * @method static Builder<static>|DeviceCommand whereErrorCode($value)
 * @method static Builder<static>|DeviceCommand whereExpiresAt($value)
 * @method static Builder<static>|DeviceCommand whereId($value)
 * @method static Builder<static>|DeviceCommand whereIp($value)
 * @method static Builder<static>|DeviceCommand whereMachineId($value)
 * @method static Builder<static>|DeviceCommand wherePublishedAt($value)
 * @method static Builder<static>|DeviceCommand whereReason($value)
 * @method static Builder<static>|DeviceCommand whereResult($value)
 * @method static Builder<static>|DeviceCommand whereStatus($value)
 * @method static Builder<static>|DeviceCommand whereType($value)
 * @method static Builder<static>|DeviceCommand whereUpdatedAt($value)
 * @method static Builder<static>|DeviceCommand whereUserAgent($value)
 * @method static Builder<static>|DeviceCommand whereUserId($value)
 * @method static Builder<static>|DeviceCommand whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'machine_id', 'user_id', 'type', 'args', 'status', 'expires_at',
    'reason', 'ip', 'user_agent',
])]
class DeviceCommand extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $command): void {
            $command->uuid ??= (string) Str::uuid7();
            $command->expires_at ??= now()->addSeconds($command->type->ttlSeconds());
        });
    }

    protected function casts(): array
    {
        return [
            'type' => CommandType::class,
            'status' => CommandStatus::class,
            'args' => 'array',
            'result' => 'array',
            'published_at' => 'datetime',
            'acked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Machine, $this> */
    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<static>  $query */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CommandStatus::Queued->value,
            CommandStatus::Published->value,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Cihaza gonderilecek kanonik govde. Imza bu dizinin JSON'u uzerinden
     * hesaplanir; alan sirasi sabit olmali, aksi halde imza tutmaz.
     *
     * @return array<string, mixed>
     */
    public function signablePayload(): array
    {
        return [
            'id' => $this->uuid,
            'type' => $this->type->value,
            'args' => $this->args ?? [],
            'iat' => $this->created_at?->getTimestamp() ?? now()->getTimestamp(),
            'exp' => $this->expires_at?->getTimestamp() ?? now()->addSeconds(30)->getTimestamp(),
        ];
    }

    public function markPublished(): void
    {
        $this->forceFill([
            'status' => CommandStatus::Published,
            'published_at' => now(),
        ])->save();
    }

    /** @param  array<string, mixed>|null  $result */
    public function markAcknowledged(bool $ok, ?string $errorCode = null, ?array $result = null): void
    {
        $this->forceFill([
            'status' => $ok ? CommandStatus::Acked : CommandStatus::Nacked,
            'acked_at' => now(),
            'error_code' => $ok ? null : $errorCode,
            'result' => $result,
        ])->save();
    }
}
