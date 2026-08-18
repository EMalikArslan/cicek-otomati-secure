<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingUser = 'waiting_user';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Acik',
            self::InProgress => 'Isleniyor',
            self::WaitingUser => 'Kullanici bekleniyor',
            self::Resolved => 'Cozuldu',
            self::Closed => 'Kapandi',
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            self::Resolved, self::Closed => false,
            default => true,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'sky',
            self::InProgress => 'amber',
            self::WaitingUser => 'violet',
            self::Resolved => 'emerald',
            self::Closed => 'slate',
        };
    }
}
