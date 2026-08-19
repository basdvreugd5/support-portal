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
     * recalculate the existing SLA deadline for the MVP. The assignment is
     * only touched when $assignmentProvided is true, so an explicit null
     * assignment clears it and an omitted one leaves it untouched.
     */
    public function handle(
        Ticket $ticket,
        ?TicketStatus $status = null,
        ?TicketPriority $priority = null,
        ?int $assignedToId = null,
        bool $assignmentProvided = false,
    ): Ticket {
        if ($status !== null) {
            $ticket->status = $status;
        }

        if ($priority !== null) {
            $ticket->priority = $priority;
        }

        if ($assignmentProvided) {
            $ticket->assigned_to_id = $assignedToId;
        }

        $ticket->save();

        return $ticket;
    }
}
