<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationEvent: string
{
    case Sale = 'sale';
    case Offline = 'offline';
    case TempAlarm = 'temp_alarm';
    case LidFailed = 'lid_failed';
    case PaymentSuspicious = 'payment_suspicious';
    case LowStock = 'low_stock';
    case DailySummary = 'daily_summary';
    case TicketReply = 'ticket_reply';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Her satis',
            self::Offline => 'Otomat cevrimdisi',
            self::TempAlarm => 'Sicaklik alarmi',
            self::LidFailed => 'Kapak acilmadi',
            self::PaymentSuspicious => 'Supheli odeme',
            self::LowStock => 'Stok azaldi',
            self::DailySummary => 'Gunluk ozet',
            self::TicketReply => 'Talebe yanit',
        };
    }

    /**
     * Kritik olaylar sessiz saatleri delip gecer - cevrimdisi kalan bir otomat
     * sabaha kadar beklenemez.
     */
    public function ignoresQuietHours(): bool
    {
        return match ($this) {
            self::Offline, self::TempAlarm, self::LidFailed, self::PaymentSuspicious => true,
            default => false,
        };
    }

    public function defaultChannels(): array
    {
        return match ($this) {
            self::Sale => [NotificationChannel::Push],
            self::DailySummary => [NotificationChannel::Mail],
            default => [NotificationChannel::Mail, NotificationChannel::Push],
        };
    }
}
