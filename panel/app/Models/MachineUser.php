<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MachineRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Kullanici <-> Otomat yetki satiri.
 *
 * Bu modelin yazma islemleri yalnizca super admin tarafindan yapilabilir;
 * kural MachineUserPolicy'de ve AuthorizesMachineAccess'te uygulanir.
 *
 * @property int $id
 * @property int $machine_id
 * @property int $user_id
 * @property MachineRole $role
 * @property array<array-key, mixed>|null $permissions
 * @property int|null $granted_by_id
 * @property Carbon|null $granted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $grantedBy
 * @property-read Machine|null $machine
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereGrantedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereGrantedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereMachineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser wherePermissions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MachineUser whereUserId($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['machine_id', 'user_id', 'role', 'permissions', 'granted_by_id', 'granted_at', 'revoked_at'])]
class MachineUser extends Pivot
{
    protected $table = 'machine_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'role' => MachineRole::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
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

    /** @return BelongsTo<User, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
