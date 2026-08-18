<?php

declare(strict_types=1);

namespace App\Enums;

enum CommandStatus: string
{
    case Queued = 'queued';
    case Published = 'published';
    case Acked = 'acked';
    case Nacked = 'nacked';
    case Expired = 'expired';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Kuyrukta',
            self::Published => 'Gonderildi',
            self::Acked => 'Karta ulasti',
            self::Nacked => 'Reddedildi',
            self::Expired => 'Zaman asimi',
            self::Failed => 'Basarisiz',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Queued, self::Published => false,
            default => true,
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::Acked;
    }

    /** Tailwind renk anahtari (UI rozetleri icin). */
    public function color(): string
    {
        return match ($this) {
            self::Acked => 'emerald',
            self::Queued, self::Published => 'amber',
            self::Nacked, self::Expired, self::Failed => 'rose',
        };
    }
}
