<?php

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
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
    expect($ticket->sla_due_at->isAfter(now()->addHours(23)))->toBeTrue();
    expect($ticket->sla_due_at->isBefore(now()->addHours(25)))->toBeTrue();
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

it('redirects guests to the login page when storing a ticket', function () {
    $this->post(route('tickets.store'), [
        'title' => 'Zonder inlog',
        'description' => 'Mag niet.',
        'priority' => 'normal',
    ])->assertRedirect(route('login'));
});
