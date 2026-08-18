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
     * Clients always create inside their own organization; agents must
     * specify an organization to create on behalf of.
     *
     * @param  array{title: string, description: string, priority: TicketPriority|string, organization_id?: int}  $data
     */
    public function handle(User $user, array $data): Ticket
    {
        if ($user->role === UserRole::Agent) {
            $organization = Organization::findOrFail($data['organization_id']);
        } else {
            $organization = Organization::findOrFail($user->organization_id);
        }

        $priority = $data['priority'] instanceof TicketPriority
            ? $data['priority']
            : TicketPriority::from($data['priority']);

        return Ticket::create([
            'organization_id' => $organization->id,
            'created_by_id' => $user->id,
            'assigned_to_id' => null,
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => TicketStatus::Open,
            'priority' => $priority,
            'sla_due_at' => now()->addHours($priority->slaHours()),
        ]);
    }
}
