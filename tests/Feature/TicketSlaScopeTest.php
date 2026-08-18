<?php

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('scopes overdue to active tickets past their deadline', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $overdueOpen = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->subHour(),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addHours(3),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Resolved,
        'sla_due_at' => now()->subHour(),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'sla_due_at' => now()->subHour(),
    ]);

    $ids = Ticket::overdue()->pluck('id')->all();

    expect($ids)->toBe([$overdueOpen->id]);
});

it('scopes due soon to active tickets within the window', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $dueSoon = Ticket::factory()->create([
        'status' => TicketStatus::InProgress,
        'sla_due_at' => now()->addHour(),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addHours(3),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->subHour(),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Resolved,
        'sla_due_at' => now()->addHour(),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Closed,
        'sla_due_at' => now()->addHour(),
    ]);

    $ids = Ticket::dueSoon()->pluck('id')->all();

    expect($ids)->toBe([$dueSoon->id]);
});

it('includes tickets at the exact start of the due soon window', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $atWindowEdge = Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addMinutes(120),
    ]);
    Ticket::factory()->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addMinutes(121),
    ]);

    $ids = Ticket::dueSoon()->pluck('id')->all();

    expect($ids)->toBe([$atWindowEdge->id]);
});
