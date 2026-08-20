<?php

namespace App\Http\Controllers;

use App\Actions\AddTicketMessageAction;
use App\Actions\CreateTicketAction;
use App\Actions\UpdateTicketAction;
use App\Enums\SlaStatus;
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
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * The current user, guaranteed present because these routes sit behind
     * the 'auth' middleware.
     */
    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('U bent niet ingelogd.');
        }

        return $user;
    }

    /**
     * Display the tickets visible to the current user.
     */
    public function index(Request $request): Response
    {
        $user = $this->authenticatedUser($request);
        $query = Ticket::query()->visibleTo($user);

        $status = null;
        $priority = null;
        $sla = null;
        $search = '';
        $organizationId = 0;

        if ($user->role === UserRole::Agent) {
            $status = $request->enum('status', TicketStatus::class);
            $priority = $request->enum('priority', TicketPriority::class);
            $sla = $request->enum('sla', SlaStatus::class);
            $search = $request->string('search')->trim()->toString();
            $organizationId = $request->integer('organization_id');

            $query
                ->when($status?->value, fn ($q, $value) => $q->where('status', $value))
                ->when($priority?->value, fn ($q, $value) => $q->where('priority', $value))
                ->when($sla === SlaStatus::OnTrack, fn ($q) => $q->onTrack())
                ->when($sla === SlaStatus::DueSoon, fn ($q) => $q->dueSoon())
                ->when($sla === SlaStatus::Overdue, fn ($q) => $q->overdue())
                ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                    $pattern = '%'.addcslashes($search, '%_\\').'%';

                    $q->whereLike('title', $pattern)
                        ->orWhereLike('description', $pattern);
                }))
                ->when($organizationId > 0, fn ($q) => $q->where('organization_id', $organizationId));
        }

        $tickets = $query
            ->with(['organization', 'assignedTo'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $filters = [
            'status' => $status->value ?? '',
            'priority' => $priority->value ?? '',
            'sla' => $sla->value ?? '',
            'search' => $search,
            'organization' => $organizationId > 0 ? (string) $organizationId : '',
        ];

        $data = [
            'tickets' => TicketResource::collection($tickets->getCollection())->resolve($request),
            'filters' => $filters,
            'pagination' => [
                'page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ];

        if ($user->role === UserRole::Agent) {
            $data['organizations'] = OrganizationResource::collection(Organization::orderBy('name')->get())->resolve($request);
        }

        return Inertia::render(
            $user->role === UserRole::Agent ? 'Agent/Tickets/Index' : 'Client/Tickets/Index',
            $data,
        );
    }

    /**
     * Show the ticket creation form.
     */
    public function create(Request $request): Response
    {
        if ($this->authenticatedUser($request)->role === UserRole::Agent) {
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
            $this->authenticatedUser($request),
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

        $user = $this->authenticatedUser($request);

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
        $assignedTo = $request->input('assigned_to_id');

        $updateTicket->handle(
            $ticket,
            $request->enum('status', TicketStatus::class),
            $request->enum('priority', TicketPriority::class),
            $assignedTo !== null ? (int) $assignedTo : null,
            $request->exists('assigned_to_id'),
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

        $addMessage->handle($this->authenticatedUser($request), $ticket, $request->validated('body'), $type);

        $request->session()->flash(
            'success',
            $type === TicketMessageType::Internal ? 'Interne notitie toegevoegd.' : 'Reactie geplaatst.',
        );

        return back();
    }
}
