<?php

namespace App\Actions;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;

class CreateTicketAction
{
    /**
     * Create a ticket on behalf of the given user.
     *
     * Clients always create inside their own organization and ignore
     * $organizationId. Agents must provide the organization to create
     * on behalf of; the store request validation guarantees it is present.
     *
     * @param  ?int  $organizationId  The organization id for agents; ignored for clients.
     */
    public function handle(User $user, string $title, string $description, TicketPriority $priority, ?int $organizationId = null): Ticket
    {
        if ($user->role === UserRole::Agent) {
            $organization = Organization::findOrFail((int) $organizationId);
        } else {
            $organization = Organization::findOrFail($user->organization_id);
        }

        return Ticket::create([
            'organization_id' => $organization->id,
            'created_by_id' => $user->id,
            'assigned_to_id' => null,
            'title' => $title,
            'description' => $description,
            'status' => TicketStatus::Open,
            'priority' => $priority,
            'sla_due_at' => now()->addHours($priority->slaHours()),
        ]);
    }
}
