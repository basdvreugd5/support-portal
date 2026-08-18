<?php

use App\Actions\UpdateTicketAction;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates the ticket status', function () {
    $ticket = Ticket::factory()->create(['status' => TicketStatus::Open]);

    $ticket = (new UpdateTicketAction)->handle($ticket, TicketStatus::InProgress);

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
});

it('updates the ticket priority', function () {
    $ticket = Ticket::factory()->create(['priority' => TicketPriority::Normal]);

    $ticket = (new UpdateTicketAction)->handle($ticket, null, TicketPriority::High);

    expect($ticket->fresh()->priority)->toBe(TicketPriority::High);
});

it('assigns a ticket to an agent', function () {
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->create();

    (new UpdateTicketAction)->handle($ticket, null, null, $agent->id);

    expect($ticket->fresh()->assigned_to_id)->toBe($agent->id);
});

it('leaves fields untouched when nothing is provided', function () {
    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::InProgress,
        'priority' => TicketPriority::High,
    ]);

    (new UpdateTicketAction)->handle($ticket);

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
    expect($ticket->fresh()->priority)->toBe(TicketPriority::High);
    expect($ticket->fresh()->assigned_to_id)->toBeNull();
});

it('does not recalculate the SLA deadline when the priority changes', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'priority' => TicketPriority::Normal,
        'sla_due_at' => now()->addHours(48),
    ]);
    $dueBefore = $ticket->sla_due_at;

    (new UpdateTicketAction)->handle($ticket, null, TicketPriority::High);

    expect($ticket->fresh()->sla_due_at->equalTo($dueBefore))->toBeTrue();
});
