<?php

declare(strict_types=1);

namespace App\Enums;

enum MachineRole: string
{
    case Owner = 'owner';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Sahip',
            self::Operator => 'Operator',
            self::Viewer => 'Izleyici',
        };
    }
}
