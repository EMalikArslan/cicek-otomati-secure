<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MachinePermission;
use App\Models\Machine;
use App\Models\User;

/**
 * Otomat yetki kurallari.
 *
 * Super admin kontrolu AppServiceProvider'daki Gate::before ile yapilir;
 * burada yalnizca normal kullanici kurallari tanimlanir. Her yetenek
 * MachinePermission enum'una birebir baglanir, boylece "her sayfa super admin
 * tarafinda acilip kapatilabilir" isteri tek noktadan islenir.
 */
class MachinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Machine $machine): bool
    {
        return $user->hasAccessToMachine($machine);
    }

    /** Otomat olusturma/silme yalnizca super admin (Gate::before ile gecer). */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::SettingsManage);
    }

    public function delete(User $user, Machine $machine): bool
    {
        return false;
    }

    // ---- Sayfa erisimleri -----------------------------------------------

    public function viewAnalytics(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::AnalyticsView);
    }

    public function exportAnalytics(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::AnalyticsExport);
    }

    public function viewSlots(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::SlotsView);
    }

    public function viewLidControl(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::LidsOpen);
    }

    public function viewRestock(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::RestockManage);
    }

    public function viewSettings(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::SettingsManage);
    }

    public function viewRecommendations(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::RecommendationsView);
    }

    // ---- Fiziksel etki doguran eylemler ---------------------------------

    public function openSlot(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::SlotsOpen);
    }

    public function openLid(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::LidsOpen);
    }

    public function toggleSlot(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::SlotsToggle);
    }

    public function editPrice(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::SlotsPriceEdit);
    }

    public function restock(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::RestockManage);
    }

    /** Cihaz kimlik bilgisi uretme/iptal: yalnizca super admin. */
    public function manageCredentials(User $user, Machine $machine): bool
    {
        return false;
    }
}
