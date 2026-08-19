<?php

use App\Enums\TicketMessageType;
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
    $this->agent = User::factory()->agent()->create();
    $this->otherAgent = User::factory()->agent()->create();
    $this->ticket = Ticket::factory()->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Resolved,
        'priority' => TicketPriority::Normal,
    ]);
});

it('renders the agent index page for agents and the client page for clients', function () {
    $this->actingAs($this->clientA)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Client/Tickets/Index'));

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Agent/Tickets/Index'));
});

it('filters tickets by status and priority', function () {
    $open = Ticket::factory()->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
    ]);
    Ticket::factory()->forOrganization($this->orgB)->create([
        'status' => TicketStatus::InProgress,
        'priority' => TicketPriority::Low,
    ]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['status' => 'open']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets', 1)->where('tickets.0.id', $open->id));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['priority' => 'low']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets', 1));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['status' => 'open', 'priority' => 'high']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets', 1)->where('tickets.0.id', $open->id));
});

it('filters tickets by SLA status', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $onTrack = Ticket::factory()->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addHours(3),
    ]);
    $dueSoon = Ticket::factory()->forOrganization($this->orgB)->create([
        'status' => TicketStatus::Open,
        'sla_due_at' => now()->addHour(),
    ]);
    $overdue = Ticket::factory()->forOrganization($this->orgA)->create([
        'status' => TicketStatus::InProgress,
        'sla_due_at' => now()->subHour(),
    ]);
    Ticket::factory()->forOrganization($this->orgB)->create([
        'status' => TicketStatus::Closed,
        'sla_due_at' => now()->subHour(),
    ]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['sla' => 'on_track']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets', 1)->where('tickets.0.id', $onTrack->id));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['sla' => 'due_soon']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets', 1)->where('tickets.0.id', $dueSoon->id));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['sla' => 'overdue']))
        ->assertInertia(fn (Assert $page) => $page->has('tickets', 1)->where('tickets.0.id', $overdue->id));
});

it('lets an agent update status, priority and assignment with feedback', function () {
    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticket), [
            'status' => 'closed',
            'priority' => 'high',
            'assigned_to_id' => $this->otherAgent->id,
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Ticket bijgewerkt.');

    $ticket = $this->ticket->fresh();

    expect($ticket->status)->toBe(TicketStatus::Closed);
    expect($ticket->priority)->toBe(TicketPriority::High);
    expect($ticket->assigned_to_id)->toBe($this->otherAgent->id);
});

it('blocks a client from updating a ticket', function () {
    $this->actingAs($this->clientA)
        ->patch(route('tickets.update', $this->ticket), ['status' => 'closed'])
        ->assertForbidden();
});

it('rejects assigning a ticket to a client user', function () {
    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticket), ['assigned_to_id' => $this->clientA->id])
        ->assertSessionHasErrors('assigned_to_id');
});

it('lets an agent clear the assignment', function () {
    $ticket = Ticket::factory()->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
        'assigned_to_id' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $ticket), ['assigned_to_id' => ''])
        ->assertRedirect()
        ->assertSessionHas('success', 'Ticket bijgewerkt.');

    expect($ticket->fresh()->assigned_to_id)->toBeNull();
});

it('keeps the assignment when it is omitted from the update', function () {
    $ticket = Ticket::factory()->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
        'assigned_to_id' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $ticket), ['status' => 'in_progress'])
        ->assertRedirect();

    expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress);
    expect($ticket->fresh()->assigned_to_id)->toBe($this->otherAgent->id);
});

it('validates the update input', function () {
    $this->actingAs($this->agent)
        ->patch(route('tickets.update', $this->ticket), ['status' => 'onbekend'])
        ->assertSessionHasErrors('status');
});

it('lets an agent post an internal note', function () {
    $this->actingAs($this->agent)
        ->post(route('tickets.reply', $this->ticket), [
            'body' => 'Interne context.',
            'type' => 'internal',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Interne notitie toegevoegd.');

    $this->assertDatabaseHas('ticket_messages', [
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->agent->id,
        'type' => 'internal',
        'body' => 'Interne context.',
    ]);
});

it('blocks a client from posting an internal note', function () {
    $this->actingAs($this->clientA)
        ->post(route('tickets.reply', $this->ticket), [
            'body' => 'Interne tekst.',
            'type' => 'internal',
        ])
        ->assertForbidden();
});

it('paginates the agent ticket list', function () {
    Ticket::factory()->count(16)->forOrganization($this->orgA)->create();

    $this->actingAs($this->agent)
        ->get(route('tickets.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 15)
            ->where('pagination.page', 1)
            ->where('pagination.last_page', 2)
            ->where('pagination.total', 17));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 2)
            ->where('pagination.page', 2)
            ->where('pagination.last_page', 2));
});

it('keeps filters active across pages', function () {
    $this->travelTo('2026-01-01 09:00:00');

    Ticket::factory()->count(16)->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::High,
        'sla_due_at' => now()->addHours(3),
    ]);
    Ticket::factory()->count(2)->forOrganization($this->orgB)->create([
        'status' => TicketStatus::Closed,
        'priority' => TicketPriority::Low,
        'sla_due_at' => now()->addHours(3),
    ]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['status' => 'open', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('pagination.total', 16)
            ->where('pagination.page', 2));
});

it('filters tickets by organization', function () {
    $orgB = $this->orgB;
    $bTicket = Ticket::factory()->forOrganization($orgB)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
    ]);
    Ticket::factory()->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
    ]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['organization_id' => $orgB->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('tickets.0.id', $bTicket->id)
            ->where('filters.organization', (string) $orgB->id));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['organization_id' => $orgB->id, 'status' => 'open']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('tickets.0.id', $bTicket->id));
});

it('filters tickets by free-text search across title and description', function () {
    $titleMatch = Ticket::factory()->forOrganization($this->orgA)->create([
        'title' => 'VPN werkt niet op de buitenlandse locaties',
        'priority' => TicketPriority::Normal,
    ]);
    $descMatch = Ticket::factory()->forOrganization($this->orgB)->create([
        'title' => 'Printerstoring vestiging Leiden',
        'description' => 'De VPN-verbinding valt telkens weg tijdens vergaderingen.',
        'priority' => TicketPriority::Normal,
    ]);
    Ticket::factory()->forOrganization($this->orgA)->create([
        'title' => 'Ongezocht ticket',
        'priority' => TicketPriority::Normal,
    ]);

    $onlyMatching = fn (array $expected) => function ($tickets) use ($expected) {
        $tickets = collect($tickets);

        return $tickets->pluck('id')->diff($expected)->isEmpty()
            && $tickets->count() === count($expected);
    };

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['search' => 'vpn']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 2)
            ->where('filters.search', 'vpn')
            ->where('tickets', $onlyMatching([$titleMatch->id, $descMatch->id])));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['search' => 'vpn-verbinding']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('tickets', $onlyMatching([$descMatch->id])));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['search' => 'printerstoring']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('tickets', $onlyMatching([$descMatch->id])));
});

it('does not treat % and _ as LIKE wildcards in search', function () {
    $percent = Ticket::factory()->forOrganization($this->orgA)->create([
        'title' => 'Factuurdownload 100% gestopt',
        'priority' => TicketPriority::Normal,
    ]);
    Ticket::factory()->forOrganization($this->orgA)->create([
        'title' => 'Factuurdownload 100X gestopt',
        'priority' => TicketPriority::Normal,
    ]);
    $underscore = Ticket::factory()->forOrganization($this->orgB)->create([
        'title' => 'VPN_profile config werkt',
        'priority' => TicketPriority::Normal,
    ]);
    Ticket::factory()->forOrganization($this->orgB)->create([
        'title' => 'VPNxprofile config werkt',
        'priority' => TicketPriority::Normal,
    ]);

    $onlyId = fn (int $id) => fn ($tickets) => collect($tickets)->count() === 1
        && collect($tickets)->first()['id'] === $id;

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['search' => '% gestopt']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('tickets', $onlyId($percent->id)));

    $this->actingAs($this->agent)
        ->get(route('tickets.index', ['search' => 'VPN_profile']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('tickets', $onlyId($underscore->id)));
});

it('keeps search and organization filters active across pages', function () {
    Ticket::factory()->count(16)->forOrganization($this->orgA)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
        'title' => 'S3 sync storing Acme',
    ]);
    Ticket::factory()->count(2)->forOrganization($this->orgB)->create([
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
        'title' => 'S3 sync storing Globex',
    ]);

    $this->actingAs($this->agent)
        ->get(route('tickets.index', [
            'search' => 'S3 sync',
            'organization_id' => $this->orgA->id,
            'page' => 2,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 1)
            ->where('filters.search', 'S3 sync')
            ->where('filters.organization', (string) $this->orgA->id)
            ->where('pagination.total', 16)
            ->where('pagination.page', 2));
});

it('lets clients ignore the agent-only search and organization filters', function () {
    Ticket::factory()->forOrganization($this->orgA)->create([
        'title' => 'VPN storing',
        'priority' => TicketPriority::Normal,
    ]);

    $this->actingAs($this->clientA)
        ->get(route('tickets.index', ['search' => 'VPN', 'organization_id' => $this->orgB->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('tickets', 2)
            ->where('filters.search', '')
            ->where('filters.organization', ''));
});

it('shows internal notes to agents but not to clients', function () {
    TicketMessage::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->agent->id,
        'type' => TicketMessageType::Internal,
    ]);
    TicketMessage::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->agent->id,
        'type' => TicketMessageType::Public,
    ]);

    $this->actingAs($this->agent)
        ->get(route('tickets.show', $this->ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Agent/Tickets/Show')
            ->has('ticket.messages', 2)
            ->has('agents', 2));

    $this->actingAs($this->clientA)
        ->get(route('tickets.show', $this->ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Client/Tickets/Show')
            ->has('ticket.messages', 1));
});
