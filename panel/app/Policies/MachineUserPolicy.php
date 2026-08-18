<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MachineUser;
use App\Models\User;

/**
 * Yetki atama kurallari.
 *
 * Ister acikca soyle diyor: "bu yetkileri kesinlikle super adminden baskasi
 * saglamayacak." Bu yuzden tum yetenekler `false` doner; yalnizca Gate::before
 * icindeki super admin kontrolu bu politikayi gecebilir. Otomat sahibi olmak
 * dahi baskasina yetki vermeye yetmez.
 */
class MachineUserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, MachineUser $machineUser): bool
    {
        // Kullanici kendi yetki satirini gorebilir (hangi izinlere sahip oldugunu bilmeli).
        return $machineUser->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MachineUser $machineUser): bool
    {
        return false;
    }

    public function delete(User $user, MachineUser $machineUser): bool
    {
        return false;
    }
}
