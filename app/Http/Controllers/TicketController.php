<?php

namespace App\Http\Controllers;

use App\Actions\AddTicketMessageAction;
use App\Actions\CreateTicketAction;
use App\Actions\UpdateTicketAction;
use App\Enums\TicketMessageType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Display the tickets visible to the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $query = Ticket::query()->visibleTo($user);

        $status = null;
        $priority = null;
        $sla = '';

        if ($user->role === UserRole::Agent) {
            $status = $request->enum('status', TicketStatus::class);
            $priority = $request->enum('priority', TicketPriority::class);
            $sla = $request->string('sla')->toString();

            $query
                ->when($status?->value, fn ($q, $value) => $q->where('status', $value))
                ->when($priority?->value, fn ($q, $value) => $q->where('priority', $value))
                ->when($sla === 'on_track', fn ($q) => $q->onTrack())
                ->when($sla === 'due_soon', fn ($q) => $q->dueSoon())
                ->when($sla === 'overdue', fn ($q) => $q->overdue());
        }

        $tickets = $query
            ->with(['organization', 'assignedTo'])
            ->latest()
            ->get();

        return Inertia::render(
            $user->role === UserRole::Agent ? 'Agent/Tickets/Index' : 'Client/Tickets/Index',
            [
                'tickets' => TicketResource::collection($tickets)->resolve($request),
                'filters' => [
                    'status' => $status->value ?? '',
                    'priority' => $priority->value ?? '',
                    'sla' => $sla,
                ],
            ],
        );
    }

    /**
     * Show the ticket creation form.
     */
    public function create(Request $request): Response
    {
        if ($request->user()->role === UserRole::Agent) {
            $organizations = Organization::orderBy('name')->get();

            return Inertia::render('Client/Tickets/Create', [
                'organizations' => OrganizationResource::collection($organizations)->resolve($request),
            ]);
        }

        return Inertia::render('Client/Tickets/Create');
    }

    /**
     * Store a newly created ticket.
     */
    public function store(StoreTicketRequest $request, CreateTicketAction $createTicket): RedirectResponse
    {
        $ticket = $createTicket->handle(
            $request->user(),
            $request->string('title')->toString(),
            $request->string('description')->toString(),
            $request->enum('priority', TicketPriority::class) ?? throw ValidationException::withMessages(['priority' => 'De prioriteit is verplicht.']),
            $request->integer('organization_id'),
        );

        $request->session()->flash('success', 'Ticket aangemaakt.');

        return redirect()->route('tickets.show', $ticket);
    }

    /**
     * Display the specified ticket with messages filtered by visibility.
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $user = $request->user();

        $ticket->load([
            'organization',
            'createdBy',
            'assignedTo',
            'messages' => fn ($query) => $query->visibleTo($user)->with('user')->oldest(),
        ]);

        if ($user->role === UserRole::Agent) {
            $agents = User::query()
                ->where('role', UserRole::Agent->value)
                ->orderBy('name')
                ->get();

            return Inertia::render('Agent/Tickets/Show', [
                'ticket' => new TicketResource($ticket),
                'agents' => UserResource::collection($agents)->resolve($request),
            ]);
        }

        return Inertia::render('Client/Tickets/Show', [
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Update the agent-managed fields of the specified ticket.
     */
    public function update(UpdateTicketRequest $request, Ticket $ticket, UpdateTicketAction $updateTicket): RedirectResponse
    {
        $updateTicket->handle(
            $ticket,
            $request->enum('status', TicketStatus::class),
            $request->enum('priority', TicketPriority::class),
            $request->has('assigned_to_id') ? $request->integer('assigned_to_id') : null,
        );

        $request->session()->flash('success', 'Ticket bijgewerkt.');

        return back();
    }

    /**
     * Store a public reply or internal note on the specified ticket.
     */
    public function reply(StoreTicketMessageRequest $request, Ticket $ticket, AddTicketMessageAction $addMessage): RedirectResponse
    {
        $type = $request->enum('type', TicketMessageType::class) ?? TicketMessageType::Public;

        $addMessage->handle($request->user(), $ticket, $request->validated('body'), $type);

        $request->session()->flash(
            'success',
            $type === TicketMessageType::Internal ? 'Interne notitie toegevoegd.' : 'Reactie geplaatst.',
        );

        return back();
    }
}
