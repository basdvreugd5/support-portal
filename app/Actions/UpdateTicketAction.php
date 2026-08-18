<?php

namespace App\Actions;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;

class UpdateTicketAction
{
    /**
     * Update a ticket's agent-managed fields.
     *
     * Only the provided fields are changed. Changing the priority does not
     * recalculate the existing SLA deadline for the MVP.
     */
    public function handle(
        Ticket $ticket,
        ?TicketStatus $status = null,
        ?TicketPriority $priority = null,
        ?int $assignedToId = null,
    ): Ticket {
        if ($status !== null) {
            $ticket->status = $status;
        }

        if ($priority !== null) {
            $ticket->priority = $priority;
        }

        if ($assignedToId !== null) {
            $ticket->assigned_to_id = $assignedToId;
        }

        $ticket->save();

        return $ticket;
    }
}
