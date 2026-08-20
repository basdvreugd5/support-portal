<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->orgA = Organization::factory()->create();
    $this->orgB = Organization::factory()->create();
    $this->clientA = User::factory()->forOrganization($this->orgA)->create();
    $this->clientB = User::factory()->forOrganization($this->orgB)->create();
    $this->agent = User::factory()->agent()->create();
    $this->ticketA = Ticket::factory()->forOrganization($this->orgA)->create();
    $this->ticketB = Ticket::factory()->forOrganization($this->orgB)->create();
});

it('redirects guests to the login page on the ticket index', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
});

it('shows a client only tickets from their own organization', function () {
    $this->actingAs($this->clientA)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Tickets/Index')
            ->has('tickets', 1)
            ->where('tickets.0.id', $this->ticketA->id));
});

it('shows an agent tickets from all organizations', function () {
    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('tickets', 2));
});

it('allows a client to view a ticket from their own organization', function () {
    $this->actingAs($this->clientA)
        ->get(route('tickets.show', $this->ticketA))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Tickets/Show')
            ->where('ticket.id', $this->ticketA->id));
});

it('does not expose internal notes to a client on ticket show', function () {
    $public = TicketMessage::factory()->create([
        'ticket_id' => $this->ticketA->id,
        'user_id' => $this->clientA->id,
    ]);
    TicketMessage::factory()->internal()->create([
        'ticket_id' => $this->ticketA->id,
        'user_id' => $this->agent->id,
    ]);

    $this->actingAs($this->clientA)
        ->get(route('tickets.show', $this->ticketA))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('ticket.messages', 1)
            ->where('ticket.messages.0.id', $public->id));
});

it('blocks a client from viewing a ticket from another organization', function () {
    $this->actingAs($this->clientA)
        ->get(route('tickets.show', $this->ticketB))
        ->assertForbidden();
});

it('allows an agent to view any ticket', function () {
    $this->actingAs($this->agent)
        ->get(route('tickets.show', $this->ticketB))
        ->assertOk();
});

it('allows a client to create a ticket with open status and an SLA deadline', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $this->actingAs($this->clientA)
        ->post(route('tickets.store'), [
            'title' => 'Productieomgeving traag',
            'description' => 'De omgeving reageert traag sinds vanmorgen.',
            'priority' => 'high',
        ])
        ->assertRedirect();

    $ticket = Ticket::where('title', 'Productieomgeving traag')->firstOrFail();

    expect($ticket->organization_id)->toBe($this->orgA->id);
    expect($ticket->created_by_id)->toBe($this->clientA->id);
    expect($ticket->status)->toBe(TicketStatus::Open);
    expect($ticket->assigned_to_id)->toBeNull();
    expect($ticket->sla_due_at->equalTo(now()->addHours(24)))->toBeTrue();
});

it('shows a success toast after creating a ticket', function () {
    $this->actingAs($this->clientA)
        ->post(route('tickets.store'), [
            'title' => 'Feedback zichtbaar',
            'description' => 'Moet een toast tonen.',
            'priority' => 'normal',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $ticket = Ticket::where('title', 'Feedback zichtbaar')->firstOrFail();

    $this->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.message', 'Ticket aangemaakt.'));
});

it('validates the ticket creation input', function () {
    $this->actingAs($this->clientA)
        ->post(route('tickets.store'), [])
        ->assertSessionHasErrors(['title', 'description', 'priority']);
});

it('forces a client ticket into their own organization regardless of the payload', function () {
    $this->actingAs($this->clientA)
        ->post(route('tickets.store'), [
            'title' => 'Poging tot ander huurdersdomein',
            'description' => 'organization_id mag niet worden overgenomen.',
            'priority' => 'normal',
            'organization_id' => $this->orgB->id,
        ])
        ->assertRedirect();

    $ticket = Ticket::where('title', 'Poging tot ander huurdersdomein')->firstOrFail();

    expect($ticket->organization_id)->toBe($this->orgA->id);
});

it('allows a client to add a public reply to their own ticket', function () {
    $this->actingAs($this->clientA)
        ->post(route('tickets.reply', $this->ticketA), ['body' => 'Bedankt voor de hulp!'])
        ->assertRedirect();

    $this->assertDatabaseHas('ticket_messages', [
        'ticket_id' => $this->ticketA->id,
        'user_id' => $this->clientA->id,
        'type' => 'public',
        'body' => 'Bedankt voor de hulp!',
    ]);
});

it('blocks a client from replying to a ticket from another organization', function () {
    $this->actingAs($this->clientA)
        ->post(route('tickets.reply', $this->ticketB), ['body' => 'Kan ik dit zien?'])
        ->assertForbidden();
});

it('validates the reply input', function () {
    $this->actingAs($this->clientA)
        ->post(route('tickets.reply', $this->ticketA), ['body' => ''])
        ->assertSessionHasErrors(['body']);
});

it('passes ticket content through unchanged without server-side mangling', function () {
    $script = '<script>alert(1)</script>';

    $this->actingAs($this->clientA)
        ->post(route('tickets.store'), [
            'title' => "Titel {$script}",
            'description' => "Beschrijving {$script}",
            'priority' => 'normal',
        ])
        ->assertRedirect();

    $ticket = Ticket::where('title', "Titel {$script}")->firstOrFail();

    $this->actingAs($this->clientA)
        ->post(route('tickets.reply', $ticket), ['body' => "Reactie {$script}"])
        ->assertRedirect();

    $this->actingAs($this->clientA)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.title', "Titel {$script}")
            ->where('ticket.description', "Beschrijving {$script}")
            ->where('ticket.messages.0.body', "Reactie {$script}"));
});

it('allows an agent to update a ticket via PATCH', function () {
    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticketA), [
            'status' => 'in_progress',
            'priority' => 'high',
            'assigned_to_id' => $this->agent->id,
        ])
        ->assertRedirect();

    expect($this->ticketA->fresh()->status)->toBe(TicketStatus::InProgress);
    expect($this->ticketA->fresh()->priority)->toBe(TicketPriority::High);
    expect($this->ticketA->fresh()->assigned_to_id)->toBe($this->agent->id);
});

it('blocks a client from updating a ticket via PATCH', function () {
    $this->actingAs($this->clientA)
        ->patch(route('tickets.update', $this->ticketA), ['status' => 'resolved'])
        ->assertForbidden();

    $this->actingAs($this->clientA)
        ->patch(route('tickets.update', $this->ticketB), ['status' => 'resolved'])
        ->assertForbidden();
});

it('rejects assigning a ticket to a non-agent user', function () {
    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticketA), ['assigned_to_id' => $this->clientB->id])
        ->assertSessionHasErrors('assigned_to_id');
});

it('rejects assigning a ticket to a non-existent user', function () {
    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticketA), ['assigned_to_id' => 999999])
        ->assertSessionHasErrors('assigned_to_id');
});

it('allows an agent to reopen a closed ticket', function () {
    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticketA), ['status' => 'closed'])
        ->assertRedirect();

    expect($this->ticketA->fresh()->status)->toBe(TicketStatus::Closed);

    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticketA), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($this->ticketA->fresh()->status)->toBe(TicketStatus::InProgress);
});

it('paginates the client ticket list within their own organization', function () {
    Ticket::factory()->count(16)->forOrganization($this->orgA)->create();

    $this->actingAs($this->clientA)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 15)
            ->where('pagination.page', 1)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 17));
});

it('keeps a client inside their own organization when query filters are passed', function () {
    $this->actingAs($this->clientA)
        ->get(route('tickets.index', [
            'search' => $this->ticketB->title,
            'organization_id' => $this->orgB->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('tickets.0.id', $this->ticketA->id));
});

it('redirects guests to the login page when storing a ticket', function () {
    $this->post(route('tickets.store'), [
        'title' => 'Zonder inlog',
        'description' => 'Mag niet.',
        'priority' => 'normal',
    ])->assertRedirect(route('login'));
});
