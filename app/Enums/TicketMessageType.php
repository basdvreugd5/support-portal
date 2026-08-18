<?php

namespace App\Enums;

enum TicketMessageType: string
{
    case Public = 'public';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Publiek',
            self::Internal => 'Interne Notitie',
        };
    }
}
