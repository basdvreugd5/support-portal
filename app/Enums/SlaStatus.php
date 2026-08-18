<?php

namespace App\Enums;

enum SlaStatus: string
{
    case OnTrack = 'on_track';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::OnTrack => 'Op schema',
            self::DueSoon => 'Bijna verlopen',
            self::Overdue => 'Verlopen',
        };
    }
}
