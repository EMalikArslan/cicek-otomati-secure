<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * PROTOKOL.md 5.1 satis akisindaki sonuc dugumleri.
 *
 * `Suspicious` ve `LidFailed` para kaybi riski tasir; ciro hesaplarina
 * dahil edilmez, alarm uretir.
 */
enum SaleStatus: string
{
    case Success = 'success';
    case Suspicious = 'suspicious';   // POS timeout - banka onaylamis olabilir
    case LidFailed = 'lid_failed';    // odeme alindi, kapak acilmadi
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Basarili',
            self::Suspicious => 'Supheli',
            self::LidFailed => 'Kapak acilmadi',
            self::Refunded => 'Iade',
        };
    }

    /** Ciro ve satis adedi hesaplarina dahil edilir mi? */
    public function countsTowardRevenue(): bool
    {
        return $this === self::Success;
    }

    public function needsAttention(): bool
    {
        return match ($this) {
            self::Suspicious, self::LidFailed => true,
            default => false,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Success => 'emerald',
            self::Suspicious, self::LidFailed => 'rose',
            self::Refunded => 'slate',
        };
    }
}
