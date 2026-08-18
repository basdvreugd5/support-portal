<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a ticket whose creator belongs to the ticket organization', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->createdBy->organization_id)->toBe($ticket->organization_id);
});

it('creates a client creator in the given organization via forOrganization', function () {
    $organization = Organization::factory()->create();

    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($ticket->organization_id)->toBe($organization->id);
    expect($ticket->createdBy->organization_id)->toBe($organization->id);
});

it('computes sla_due_at from the priority at creation time', function () {
    $ticket = Ticket::factory()->create();

    $expected = $ticket->created_at->copy()->addHours($ticket->priority->slaHours());

    expect($ticket->sla_due_at->format('Y-m-d H:i'))->toBe($expected->format('Y-m-d H:i'));
});

it('supports assignment to a support agent', function () {
    $agent = User::factory()->agent()->create();

    $ticket = Ticket::factory()->assigned($agent)->create();

    expect($ticket->assigned_to_id)->toBe($agent->id);
});

it('defaults to unassigned', function () {
    $ticket = Ticket::factory()->create();

    expect($ticket->assigned_to_id)->toBeNull();
});
