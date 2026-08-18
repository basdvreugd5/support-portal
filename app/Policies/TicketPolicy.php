<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->role === UserRole::Agent) {
            return true;
        }

        return $user->organization_id === $ticket->organization_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Agent || $user->organization_id !== null;
    }

    /**
     * Determine whether the user can reply to the model.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::Agent;
    }

    /**
     * Determine whether the user can assign the model.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::Agent;
    }

    /**
     * Determine whether the user can add an internal note to the model.
     */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::Agent;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->role === UserRole::Agent;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
