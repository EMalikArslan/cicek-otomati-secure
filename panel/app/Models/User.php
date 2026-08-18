<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MachinePermission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $phone
 * @property bool $is_super_admin
 * @property string $status
 * @property string $locale
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Machine> $machines
 * @property-read int|null $machines_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, NotificationSetting> $notificationSettings
 * @property-read int|null $notification_settings_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Machine> $ownedMachines
 * @property-read int|null $owned_machines_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Permission> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Ticket> $tickets
 * @property-read int|null $tickets_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User permission($permissions, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User role($roles, ?string $guard = null, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User team($teams, bool $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsSuperAdmin($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastLoginIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutRole($roles, ?string $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTeam($teams)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Fillable(['name', 'email', 'phone', 'password', 'is_super_admin', 'status', 'locale', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** Istek suresince cozulen otomat izinleri (N+1 sorgu engeli). */
    private ?Collection $resolvedMachineAccess = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ---- Iliskiler ------------------------------------------------------

    /** @return BelongsToMany<Machine, $this> */
    public function machines(): BelongsToMany
    {
        return $this->belongsToMany(Machine::class)
            ->withPivot(['role', 'permissions', 'granted_by_id', 'granted_at', 'revoked_at'])
            ->withTimestamps()
            ->wherePivotNull('revoked_at');
    }

    /** @return HasMany<Machine, $this> */
    public function ownedMachines(): HasMany
    {
        return $this->hasMany(Machine::class, 'owner_user_id');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @return HasMany<NotificationSetting, $this> */
    public function notificationSettings(): HasMany
    {
        return $this->hasMany(NotificationSetting::class);
    }

    // ---- Yetki ----------------------------------------------------------

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Bu kullanicinin belirtilen otomatta belirtilen izni var mi?
     *
     * Super admin her seye erisir (Gate::before ile de desteklenir), ancak
     * kontrol burada da yapilir ki dogrudan cagrilarda da gecerli olsun.
     */
    public function canOnMachine(Machine|int $machine, MachinePermission $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->isActive()) {
            return false;
        }

        $machineId = $machine instanceof Machine ? $machine->id : $machine;
        $access = $this->machineAccess()->get($machineId);

        if ($access === null) {
            return false;
        }

        return (bool) ($access['permissions'][$permission->value] ?? false);
    }

    /** Otomata herhangi bir erisimi var mi? */
    public function hasAccessToMachine(Machine|int $machine): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $machineId = $machine instanceof Machine ? $machine->id : $machine;

        return $this->isActive() && $this->machineAccess()->has($machineId);
    }

    /** @return array<string, bool> */
    public function permissionsForMachine(Machine|int $machine): array
    {
        if ($this->isSuperAdmin()) {
            return array_reduce(
                MachinePermission::cases(),
                function (array $carry, MachinePermission $permission): array {
                    $carry[$permission->value] = true;

                    return $carry;
                },
                []
            );
        }

        $machineId = $machine instanceof Machine ? $machine->id : $machine;

        return $this->machineAccess()->get($machineId)['permissions'] ?? [];
    }

    /**
     * machine_id => ['role' => ..., 'permissions' => [...]] esleme tablosu.
     *
     * @return Collection<int, array{role: string, permissions: array<string, bool>}>
     */
    public function machineAccess(): Collection
    {
        return $this->resolvedMachineAccess ??= MachineUser::query()
            ->where('user_id', $this->id)
            ->whereNull('revoked_at')
            ->get(['machine_id', 'role', 'permissions'])
            ->mapWithKeys(fn (MachineUser $row): array => [
                $row->machine_id => [
                    'role' => $row->role,
                    'permissions' => $row->permissions ?? [],
                ],
            ]);
    }

    public function forgetMachineAccess(): void
    {
        $this->resolvedMachineAccess = null;
    }

    /** @return list<int> */
    public function accessibleMachineIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Machine::query()->pluck('id')->all();
        }

        return $this->machineAccess()->keys()->all();
    }
}
