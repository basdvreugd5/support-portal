<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client User',
            self::Agent => 'Support Agent',
        };
    }
}
