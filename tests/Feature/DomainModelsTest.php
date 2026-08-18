<?php

use App\Enums\TicketMessageType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes user factory states for clients and agents', function () {
    $client = User::factory()->create();
    $agent = User::factory()->agent()->create();

    expect($client->role)->toBe(UserRole::Client);
    expect($client->organization_id)->not->toBeNull();
    expect($agent->role)->toBe(UserRole::Agent);
    expect($agent->organization_id)->toBeNull();
});

it('ties a ticket to its organization, creator, assignee and messages', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $agent = User::factory()->agent()->create();

    $ticket = Ticket::factory()->forOrganization($organization, $client)->assigned($agent)->create();

    expect($ticket->organization->id)->toBe($organization->id);
    expect($ticket->createdBy->id)->toBe($client->id);
    expect($ticket->assignedTo->id)->toBe($agent->id);

    $message = TicketMessage::factory()->create(['ticket_id' => $ticket->id]);

    expect($ticket->messages)->toHaveCount(1);
    expect($message->ticket->id)->toBe($ticket->id);
});

it('ties a user to their organization, created tickets and messages', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $agent = User::factory()->agent()->create();

    $ticket = Ticket::factory()->forOrganization($organization, $client)->create();
    TicketMessage::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $client->id]);

    expect($client->organization->id)->toBe($organization->id);
    expect($client->createdTickets)->toHaveCount(1);
    expect($client->messages)->toHaveCount(1);
    expect($agent->assignedTickets)->toHaveCount(0);
});

it('supports the internal message factory state', function () {
    $message = TicketMessage::factory()->internal()->create();

    expect($message->type)->toBe(TicketMessageType::Internal);
});
