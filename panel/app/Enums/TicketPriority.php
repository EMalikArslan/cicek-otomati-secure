<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Talep aciliyeti. Arayuzde kayar secici (slider) olarak sunulur;
 * her kademe tahmini donus suresini anlik gosterir.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Dusuk',
            self::Normal => 'Normal',
            self::High => 'Yuksek',
            self::Urgent => 'Acil',
        };
    }

    /** Kayar secicideki sira (0-3). */
    public function level(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Urgent => 3,
        };
    }

    public static function fromLevel(int $level): self
    {
        return match (max(0, min(3, $level))) {
            0 => self::Low,
            1 => self::Normal,
            2 => self::High,
            default => self::Urgent,
        };
    }

    /** Hedeflenen ilk yanit suresi (saat). SLA sayaci bunu kullanir. */
    public function firstResponseHours(): int
    {
        return match ($this) {
            self::Low => 72,
            self::Normal => 24,
            self::High => 8,
            self::Urgent => 2,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => 'slate',
            self::Normal => 'sky',
            self::High => 'amber',
            self::Urgent => 'rose',
        };
    }
}
