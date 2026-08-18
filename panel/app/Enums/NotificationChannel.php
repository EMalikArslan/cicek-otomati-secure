<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationChannel: string
{
    case Mail = 'mail';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
    case Push = 'push';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'E-posta',
            self::Sms => 'SMS',
            self::WhatsApp => 'WhatsApp',
            self::Push => 'Tarayici bildirimi',
        };
    }

    /** Gonderim ucreti var mi? (bütce uyarilari ve varsayilanlar icin) */
    public function hasCost(): bool
    {
        return match ($this) {
            self::Sms, self::WhatsApp => true,
            default => false,
        };
    }

    /**
     * KVKK/IYS kapsaminda alicidan acik riza gerektiren kanallar.
     * Riza kaydi olmadan bu kanallardan gonderim yapilmaz.
     */
    public function requiresConsent(): bool
    {
        return match ($this) {
            self::Sms, self::WhatsApp, self::Mail => true,
            self::Push => false,
        };
    }
}
