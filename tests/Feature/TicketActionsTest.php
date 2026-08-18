<?php

use App\Actions\AddTicketMessageAction;
use App\Actions\CalculateSlaDeadlineAction;
use App\Actions\CreateTicketAction;
use App\Enums\TicketMessageType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a ticket for a client inside their own organization', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();

    $ticket = (new CreateTicketAction(new CalculateSlaDeadlineAction))->handle(
        $client,
        'Nieuwe VM aanvragen',
        'Wij hebben een extra VM nodig.',
        TicketPriority::Normal,
    );

    expect($ticket->organization_id)->toBe($organization->id);
    expect($ticket->created_by_id)->toBe($client->id);
    expect($ticket->assigned_to_id)->toBeNull();
    expect($ticket->status)->toBe(TicketStatus::Open);
    expect($ticket->sla_due_at->isAfter(now()->addHours(47)))->toBeTrue();
    expect($ticket->sla_due_at->isBefore(now()->addHours(49)))->toBeTrue();
});

it('creates a ticket for an agent on behalf of a chosen organization', function () {
    $organization = Organization::factory()->create();
    $agent = User::factory()->agent()->create();

    $ticket = (new CreateTicketAction(new CalculateSlaDeadlineAction))->handle(
        $agent,
        'Ticket namens klant',
        'Ingediend door de supportagent.',
        TicketPriority::Low,
        $organization->id,
    );

    expect($ticket->organization_id)->toBe($organization->id);
    expect($ticket->created_by_id)->toBe($agent->id);
    expect($ticket->sla_due_at->isAfter(now()->addHours(71)))->toBeTrue();
    expect($ticket->sla_due_at->isBefore(now()->addHours(73)))->toBeTrue();
});

it('adds a public reply by default', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    $message = (new AddTicketMessageAction)->handle($client, $ticket, 'Dank voor de hulp.');

    expect($message->ticket_id)->toBe($ticket->id);
    expect($message->user_id)->toBe($client->id);
    expect($message->type)->toBe(TicketMessageType::Public);
    expect($message->body)->toBe('Dank voor de hulp.');
});

it('adds an internal note when explicitly requested', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    $message = (new AddTicketMessageAction)->handle($agent, $ticket, 'Interne context.', TicketMessageType::Internal);

    expect($message->type)->toBe(TicketMessageType::Internal);
    expect($message->user_id)->toBe($agent->id);
});
