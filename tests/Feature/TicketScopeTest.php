<?php

use App\Enums\TicketMessageType;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restricts a client query to tickets from their own organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $client = User::factory()->forOrganization($ownOrg)->create();
    $ownTicket = Ticket::factory()->forOrganization($ownOrg)->create();
    $otherTicket = Ticket::factory()->forOrganization($otherOrg)->create();

    $visibleIds = Ticket::visibleTo($client)->pluck('id')->all();

    expect($visibleIds)->toBe([$ownTicket->id])->not->toContain($otherTicket->id);
});

it('hides every ticket from a client without an organization', function () {
    $ticket = Ticket::factory()->create();
    $client = User::factory()->create([
        'role' => UserRole::Client,
        'organization_id' => null,
    ]);

    expect(Ticket::visibleTo($client)->get())->toHaveCount(0);
});

it('gives an agent access to all tickets', function () {
    Organization::factory()->count(2)->create()->each(function (Organization $organization) {
        Ticket::factory()->forOrganization($organization)->create();
    });
    $agent = User::factory()->agent()->create();

    expect(Ticket::visibleTo($agent)->get())->toHaveCount(2);
});

it('only exposes public messages to clients', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();
    $public = TicketMessage::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $client->id,
        'type' => TicketMessageType::Public,
    ]);
    $internal = TicketMessage::factory()->internal()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $client->id,
    ]);

    expect($ticket->messages()->visibleTo($client)->pluck('id')->all())->toBe([$public->id]);
    expect($ticket->messages()->visibleTo($client)->pluck('id')->all())->not->toContain($internal->id);
});

it('exposes both public and internal messages to agents', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();
    TicketMessage::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $client->id,
        'type' => TicketMessageType::Public,
    ]);
    TicketMessage::factory()->internal()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $client->id,
    ]);

    expect($ticket->messages()->visibleTo($agent)->get())->toHaveCount(2);
});
