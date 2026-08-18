<?php

use App\Enums\TicketMessageType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Resources\TicketResource;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('formats ticket enums with a value and a label', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
        'sla_due_at' => now()->addHours(30),
    ]);

    $array = (new TicketResource($ticket))->resolve();

    expect($array['status'])->toBe(['value' => 'open', 'label' => 'Open']);
    expect($array['priority'])->toBe(['value' => 'high', 'label' => 'Hoog']);
    expect($array['sla_status'])->toBe(['value' => 'on_track', 'label' => 'Op schema']);
});

it('never includes internal messages in a client response', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();
    TicketMessage::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $agent->id,
        'type' => 'public',
        'body' => 'Voor iedereen zichtbaar.',
    ]);
    TicketMessage::factory()->internal()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $agent->id,
        'body' => 'Interne notitie: geheime context.',
    ]);

    $this->actingAs($client)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('ticket.messages', 1)
            ->where('ticket.messages.0.type.value', 'public'));
});

it('includes internal messages in an agent response', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();
    TicketMessage::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $client->id,
        'type' => TicketMessageType::Public,
        'body' => 'Voor iedereen zichtbaar.',
        'created_at' => now()->subMinutes(5),
    ]);
    TicketMessage::factory()->internal()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $agent->id,
        'body' => 'Interne notitie: geheime context.',
        'created_at' => now()->subMinutes(4),
    ]);

    $this->actingAs($agent)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('ticket.messages', 2)
            ->where('ticket.messages.0.type.value', 'public')
            ->where('ticket.messages.1.type.value', 'internal'));
});
