<?php

use App\Enums\SlaStatus;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('marks a ticket on track well before the deadline', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addHours(3),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::OnTrack);
});

it('marks a ticket due soon exactly at the start of the window', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addMinutes(120),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::DueSoon);
});

it('marks a ticket due soon within the two hour window', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addMinutes(90),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::DueSoon);
});

it('marks a ticket overdue at the exact deadline', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now(),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Overdue);
});

it('marks a ticket overdue after the deadline', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::InProgress,
        'sla_due_at' => now()->subMinutes(1),
    ]);

    expect($ticket->slaStatus())->toBe(SlaStatus::Overdue);
});

it('returns no sla status for a resolved ticket', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Resolved,
        'sla_due_at' => now()->subHours(10),
    ]);

    expect($ticket->slaStatus())->toBeNull();
});

it('returns no sla status for a closed ticket', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $ticket = Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'sla_due_at' => now()->subHours(10),
    ]);

    expect($ticket->slaStatus())->toBeNull();
});
