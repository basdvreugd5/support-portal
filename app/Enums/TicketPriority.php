<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Laag',
            self::Normal => 'Normaal',
            self::High => 'Hoog',
        };
    }

    public function slaHours(): int
    {
        return match ($this) {
            self::High => 24,
            self::Normal => 48,
            self::Low => 72,
        };
    }
}
