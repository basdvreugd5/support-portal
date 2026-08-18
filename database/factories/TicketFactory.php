<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var TicketPriority $priority */
        $priority = $this->faker->randomElement(TicketPriority::cases());

        return [
            'organization_id' => Organization::factory(),
            'created_by_id' => function (array $attributes): int {
                $organization = Organization::findOrFail($attributes['organization_id']);

                return User::factory()->forOrganization($organization)->create()->id;
            },
            'assigned_to_id' => null,
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(3),
            'status' => $this->faker->randomElement(TicketStatus::cases()),
            'priority' => $priority,
            'sla_due_at' => now()->addHours($priority->slaHours()),
        ];
    }

    /**
     * Tie the ticket to an organization and, by default, a client of that
     * organization as creator.
     */
    public function forOrganization(Organization $organization, ?User $creator = null): static
    {
        return $this->state(fn () => [
            'organization_id' => $organization->id,
            'created_by_id' => ($creator ?? User::factory()->forOrganization($organization)->create())->id,
        ]);
    }

    /**
     * Assign the ticket to the given support agent.
     */
    public function assigned(User $agent): static
    {
        return $this->state(fn () => [
            'assigned_to_id' => $agent->id,
        ]);
    }
}
