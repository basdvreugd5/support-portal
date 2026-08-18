<?php

namespace App\Http\Controllers;

use App\Actions\AddTicketMessageAction;
use App\Actions\CreateTicketAction;
use App\Enums\TicketPriority;
use App\Http\Requests\StoreTicketMessageRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
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
        $tickets = Ticket::query()
            ->visibleTo($request->user())
            ->with(['organization', 'createdBy'])
            ->latest()
            ->get();

        return Inertia::render('Client/Tickets/Index', [
            'tickets' => TicketResource::collection($tickets)->resolve($request),
        ]);
    }

    /**
     * Show the ticket creation form.
     */
    public function create(): Response
    {
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

        $ticket->load([
            'organization',
            'createdBy',
            'assignedTo',
            'messages' => fn ($query) => $query->visibleTo($request->user())->with('user')->oldest(),
        ]);

        return Inertia::render('Client/Tickets/Show', [
            'ticket' => new TicketResource($ticket),
        ]);
    }

    /**
     * Store a public reply on the specified ticket.
     */
    public function reply(StoreTicketMessageRequest $request, Ticket $ticket, AddTicketMessageAction $addMessage): RedirectResponse
    {
        $addMessage->handle($request->user(), $ticket, $request->validated('body'));

        $request->session()->flash('success', 'Reactie geplaatst.');

        return back();
    }
}
