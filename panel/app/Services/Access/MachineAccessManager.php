<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Enums\MachinePermission;
use App\Enums\MachineRole;
use App\Models\Machine;
use App\Models\MachineUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Otomat yetkilerini veren/alan tek nokta.
 *
 * Ister: "bu yetkileri kesinlikle super adminden baskasi saglamayacak."
 * Bu kural burada zorunlu tutulur - UI'da butonu gizlemek yeterli degildir,
 * cunku Livewire cagrilari dogrudan da tetiklenebilir.
 */
class MachineAccessManager
{
    /**
     * @param  array<string, bool>|null  $permissions  null ise rolun varsayilanlari kullanilir
     *
     * @throws AuthorizationException
     */
    public function grant(
        User $actor,
        Machine $machine,
        User $target,
        MachineRole $role,
        ?array $permissions = null,
    ): MachineUser {
        $this->assertSuperAdmin($actor);

        $access = MachineUser::updateOrCreate(
            ['machine_id' => $machine->id, 'user_id' => $target->id],
            [
                'role' => $role,
                'permissions' => $this->sanitize($permissions ?? MachinePermission::defaultsForRole($role)),
                'granted_by_id' => $actor->id,
                'granted_at' => now(),
                'revoked_at' => null,
            ],
        );

        activity('machine_access')
            ->performedOn($machine)
            ->causedBy($actor)
            ->withProperties([
                'target_user_id' => $target->id,
                'role' => $role->value,
                'permissions' => $access->permissions,
            ])
            ->log('Otomat yetkisi verildi');

        $target->forgetMachineAccess();

        return $access;
    }

    /**
     * @param  array<string, bool>  $permissions
     *
     * @throws AuthorizationException
     */
    public function updatePermissions(User $actor, MachineUser $access, array $permissions): MachineUser
    {
        $this->assertSuperAdmin($actor);

        $before = $access->permissions ?? [];
        $access->update(['permissions' => $this->sanitize($permissions)]);

        activity('machine_access')
            ->performedOn($access->machine)
            ->causedBy($actor)
            ->withProperties([
                'target_user_id' => $access->user_id,
                'before' => $before,
                'after' => $access->permissions,
            ])
            ->log('Otomat izinleri guncellendi');

        $access->user?->forgetMachineAccess();

        return $access;
    }

    /** @throws AuthorizationException */
    public function revoke(User $actor, MachineUser $access): void
    {
        $this->assertSuperAdmin($actor);

        $access->update(['revoked_at' => now()]);

        activity('machine_access')
            ->performedOn($access->machine)
            ->causedBy($actor)
            ->withProperties(['target_user_id' => $access->user_id])
            ->log('Otomat yetkisi kaldirildi');

        $access->user?->forgetMachineAccess();
    }

    /**
     * Bilinmeyen izin anahtarlarini atar ve degerleri boolean'a zorlar.
     * Istemciden gelen JSON'un semayi kirletmesini engeller.
     *
     * @param  array<string, mixed>  $permissions
     * @return array<string, bool>
     */
    private function sanitize(array $permissions): array
    {
        $clean = [];

        foreach (MachinePermission::cases() as $permission) {
            $clean[$permission->value] = (bool) ($permissions[$permission->value] ?? false);
        }

        return $clean;
    }

    /** @throws AuthorizationException */
    private function assertSuperAdmin(User $actor): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Otomat yetkilerini yalnizca super admin duzenleyebilir.');
        }
    }
}
