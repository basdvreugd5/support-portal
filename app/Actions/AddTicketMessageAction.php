<?php

namespace App\Actions;

use App\Enums\TicketMessageType;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;

class AddTicketMessageAction
{
    /**
     * Add a public reply or internal note to a ticket after authorization.
     *
     * Defaults to a public reply; internal notes require an explicit type
     * and agent authorization handled by the caller.
     */
    public function handle(User $user, Ticket $ticket, string $body, TicketMessageType $type = TicketMessageType::Public): TicketMessage
    {
        return TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'type' => $type,
            'body' => $body,
        ]);
    }
}
