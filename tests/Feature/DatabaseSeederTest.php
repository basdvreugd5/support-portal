<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds the planned organizations and users', function () {
    $this->seed();

    expect(Organization::query()->pluck('name')->all())->toContain('Acme BV', 'Globex Corp');

    $users = User::query()->get();

    expect($users)->toHaveCount(5);
    expect($users->pluck('email')->all())->toContain(
        'client1@acme.test',
        'client2@acme.test',
        'client@globex.test',
        'agent1@support.test',
        'agent2@support.test',
    );

    $clients = $users->where('role', UserRole::Client);
    $agents = $users->where('role', UserRole::Agent);

    expect($clients)->toHaveCount(3);
    expect($agents)->toHaveCount(2);

    $agents->each(fn (User $agent) => expect($agent->organization_id)->toBeNull());
    $clients->each(fn (User $client) => expect($client->organization_id)->not->toBeNull());
});

it('seeds tickets covering the planned demo scenarios', function () {
    $this->seed();

    $tickets = Ticket::query()->get();

    expect($tickets)->not->toBeEmpty();

    foreach (TicketStatus::cases() as $status) {
        expect($tickets->where('status', $status))->not->toBeEmpty();
    }

    foreach (TicketPriority::cases() as $priority) {
        expect($tickets->where('priority', $priority))->not->toBeEmpty();
    }

    $overdue = $tickets->filter(fn (Ticket $ticket) => $ticket->sla_due_at->isPast());
    $dueSoon = $tickets->filter(fn (Ticket $ticket) => $ticket->sla_due_at->between(now(), now()->addHours(2)));
    $onTrack = $tickets->filter(fn (Ticket $ticket) => $ticket->sla_due_at->greaterThan(now()->addHours(2)));

    expect($overdue)->not->toBeEmpty();
    expect($dueSoon)->not->toBeEmpty();
    expect($onTrack)->not->toBeEmpty();

    expect($tickets->whereNotNull('assigned_to_id'))->not->toBeEmpty();
    expect($tickets->whereNull('assigned_to_id'))->not->toBeEmpty();

    $acme = Organization::query()->where('name', 'Acme BV')->value('id');
    $globex = Organization::query()->where('name', 'Globex Corp')->value('id');

    expect($tickets->where('organization_id', $acme))->not->toBeEmpty();
    expect($tickets->where('organization_id', $globex))->not->toBeEmpty();
});

it('seeds messages for the ticket conversation', function () {
    $this->seed();

    expect(DB::table('ticket_messages')->count())->toBeGreaterThan(0);
});
