<?php

declare(strict_types=1);

namespace App\Enums;

enum CommandType: string
{
    case OpenSlot = 'open_slot';
    case OpenLid = 'open_lid';
    case SetSlotConfig = 'set_slot_config';
    case SetPrice = 'set_price';
    case Ping = 'ping';
    case Reboot = 'reboot';

    public function label(): string
    {
        return match ($this) {
            self::OpenSlot => 'Goz ac',
            self::OpenLid => 'Kapak ac',
            self::SetSlotConfig => 'Goz yapilandirmasi',
            self::SetPrice => 'Fiyat guncelle',
            self::Ping => 'Canlilik testi',
            self::Reboot => 'Yeniden baslat',
        };
    }

    /** Fiziksel etki doguran komutlar ayri hiz limitine ve audit vurgusuna tabidir. */
    public function isPhysical(): bool
    {
        return match ($this) {
            self::OpenSlot, self::OpenLid => true,
            default => false,
        };
    }

    /** Komutun gecerlilik suresi (saniye). Gec ulasan komut cihazda reddedilir. */
    public function ttlSeconds(): int
    {
        return $this->isPhysical() ? 30 : 300;
    }

    public function requiredPermission(): MachinePermission
    {
        return match ($this) {
            self::OpenSlot => MachinePermission::SlotsOpen,
            self::OpenLid => MachinePermission::LidsOpen,
            self::SetSlotConfig => MachinePermission::RestockManage,
            self::SetPrice => MachinePermission::SlotsPriceEdit,
            self::Ping, self::Reboot => MachinePermission::SettingsManage,
        };
    }
}
