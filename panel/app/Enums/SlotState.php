<?php

declare(strict_types=1);

namespace App\Enums;

enum SlotState: string
{
    case Full = 'full';
    case Empty = 'empty';
    case Reserved = 'reserved';
    case Fault = 'fault';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Dolu',
            self::Empty => 'Bos',
            self::Reserved => 'Rezerve',
            self::Fault => 'Arizali',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Full => 'emerald',
            self::Empty => 'slate',
            self::Reserved => 'sky',
            self::Fault => 'rose',
        };
    }

    public function isSellable(): bool
    {
        return $this === self::Full;
    }
}
