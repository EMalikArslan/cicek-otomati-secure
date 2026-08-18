<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MachinePermission;
use App\Models\Machine;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /** Kullanici yalnizca acik kendi talebine mesaj ekleyebilir. */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->id && $ticket->status->isOpen();
    }

    /** Atama, ic not, durum degistirme: super admin (Gate::before). */
    public function manage(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /** Talep bir otomata baglanacaksa kullanicinin o otomata erisimi olmali. */
    public function attachMachine(User $user, Machine $machine): bool
    {
        return $user->canOnMachine($machine, MachinePermission::TicketsCreate);
    }
}
