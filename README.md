# Support Ticket Portal

A small, production-minded support ticket portal built as an MVP/prototype. Two roles — **client users** (employees of a customer organization) and **support agents** — work with organization-scoped tickets, public conversations, agent-only internal notes, and a priority-based SLA engine.

## Stack

| Area                    | Choice                     | Why                                                                                         |
| ----------------------- | -------------------------- | ------------------------------------------------------------------------------------------- |
| Backend                 | Laravel 13 (PHP 8.4)       | Conventional Laravel features, mature ecosystem                                             |
| Authentication          | Laravel Breeze + Fortify   | Standard session authentication, scoped to login/logout only                                |
| Database                | MySQL / MariaDB            | Requirement                                                                                 |
| Frontend                | Vue 3                      | Requirement, rendered as an Inertia SPA                                                     |
| Server/client bridge    | Inertia.js v3              | Explicitly allowed; avoids Vue Router / API / token infrastructure                          |
| Styling                 | Tailwind CSS + shadcn/vue  | Fast, consistent UI                                                                         |
| Build tool              | Vite + `@inertiajs/vite`   | Standard Laravel tooling, auto Wayfinder type generation                                     |
| ORM                     | Eloquent                   | Laravel convention                                                                          |
| Validation              | Form Requests              | Validation is kept out of controllers                                                        |
| Authorization           | Policies + query scopes    | Defense in depth, enforced server-side                                                      |
| Data transformation     | `JsonResource`             | Explicit boundary between backend and Vue                                                   |
| Business logic          | Actions + Enums            | Only where business rules justify it                                                         |
| Testing                 | Pest                       | Expressive Laravel tests                                                                    |
| Scripted routes         | Laravel Wayfinder          | Typed TS route/action helpers generated from routes                                          |

Deliberately **not** used: Livewire, Volt, Filament, Vue Router, Pinia, Sanctum-as-SPA-auth, JWT, a separate `/api` REST architecture, repositories, generic service layers, Spatie Permission, WebSockets, queues/events (no current requirement).

## Architecture

```text
Browser
   ↓
Vue 3 + Inertia (no Vue Router)
   ↓
Laravel routes/controllers
   ↓
Form Requests / Policies / Actions
   ↓
Eloquent
   ↓
MySQL/MariaDB
```

Key boundaries:

- **Controllers** orchestrate; **Actions** own business rules; **Form Requests** validate; **Policies** authorize resources; **query scopes** restrict data at the SQL level.
- **`JsonResource` classes** are the only data boundary to the frontend. Enums are shared as `{ value, label }`; no presentation/colors live in PHP enums.
- **Shared Inertia props** (`HandleInertiaRequests`) provide `auth.user` (via a trimmed `UserResource`) and `flash.success` / `flash.error`; mutations show a synthesized toast.
- Vue is **never** a security boundary — anything sensitive is filtered before it leaves the server.

## Domain model

```text
Organization
    ├── Users (its client users)
    └── Tickets
          └── TicketMessages (public replies + internal notes)

User
    ├── createdTickets        (created_by_id)
    ├── assignedTickets       (assigned_to_id)
    └── messages
```

Constraints enforced in the database and application:

- A **client user** belongs to exactly one organization (`organization_id` required).
- A **support agent** has `organization_id = NULL`.
- `tickets.organization_id` is NOT NULL; `assigned_to_id` may only reference a user with `role = agent`.

## Roles & permissions

| Ability                         | Client | Agent |
| ------------------------------- | :----: | :---: |
| View own organization tickets   |   ✓    |   ✓   |
| View other organizations' tickets|   ✗    |   ✓   |
| Create a ticket                 |   ✓    |   ✓   |
| Add a public reply              |   ✓    |   ✓   |
| Add an internal note            |   ✗    |   ✓   |
| Change status                   |   ✗    |   ✓   |
| Change priority                 |   ✗    |   ✓   |
| Assign a ticket                 |   ✗    |   ✓   |

Authorization is enforced twice:

1. **Query level** — `Ticket::visibleTo($user)` restricts list queries to the user's own organization (clients) or all tickets (agents).
2. **Policy level** — `TicketPolicy::view()` denies direct URLs to tickets outside the client's organization with a 403; `UpdateTicketRequest` / `StoreTicketMessageRequest` authorize `update`, `reply` and `addInternalNote`.

`TicketPolicy` implements only the custom, task-oriented abilities the app uses (`view`, `create`, `update`, `reply`, `assign`, `addInternalNote`); the default Laravel CRUD boilerplate (`viewAny`, `delete`, `restore`, `forceDelete`) is intentionally omitted.

## Internal notes — hard security boundary

Internal notes (`TicketMessageType::Internal`) are visible **only to agents**. They are excluded from client responses at every layer:

```text
Authorization (role)
   ↓
filtered query (agents: public + internal, clients: public only)
   ↓
JsonResource
   ↓
Inertia props
   ↓
Vue
```

No client request ever encounters an internal note, not even as a hidden Inertia prop. This is explicitly tested.

## SLA rules

When a ticket is created, `sla_due_at` is derived from the initial priority (`CalculateSlaDeadlineAction`):

| Priority | SLA duration |
| -------- | :----------: |
| High     |    24 h      |
| Normal   |    48 h      |
| Low      |    72 h      |

`SlaStatus` is a **derived, non-persisted** enum (`on_track` / `due_soon` / `overdue`):

| Status     | Rule                                                     |
| ---------- | -------------------------------------------------------- |
| `overdue`  | `now >= sla_due_at`                                        |
| `due_soon` | `sla_due_at - now <= 120 minutes` (fixed 2 h window)       |
| `on_track` | all remaining active tickets                                |

All SLA comparisons use `Carbon::now()` with the application timezone (`config/app.php` — `UTC` by default); there is no business-hours clock.

`resolved` and `closed` tickets have **no active SLA warning**. Agents can filter the ticket list by status, priority, and SLA state — the `overdue()`, `dueSoon()` and `onTrack()` scopes mirror the same lifecycle rules at SQL level.

**Priority changes do not recalculate `sla_due_at`** (deliberate MVP decision — it keeps the lifecycle simple and predictable; a production version would decide this with the business).

## Scope

Implemented:

- Session login/logout (users created via seeders)
- Organization-scoped ticket list, creation, and detail pages for clients
- Public replies on tickets
- Agent workspace: full ticket overview, filters (status/priority/SLA), status/priority changes, assignment (including explicit unassignment), public replies, and internal notes
- Pagination (15 tickets per page) for both client and agent lists, with filters preserved across pages
- Flash feedback (success/error toasts)
- Deterministic, demo-ready seeder dataset

## Deliberately omitted

Scoped out of this prototype:

- Notifications
- Attachments / file uploads
- Audit trail
- Configurable / organization-specific SLA
- Advanced search & extra filters (organization, free-text)
- Granular/custom permissions
- Public registration, password reset, email verification, 2FA
- WebSockets / real-time updates

See *Next Steps* for how these could evolve without rearchitecting.

## Next steps / production evolution

- **Permissions** — if roles become dynamic, layer Spatie Laravel Permission on top of the existing policies.
- **SLA** — business-hours aware policies, pausing, escalation, response vs resolution SLA, organization-specific SLA.
- **Notifications** — Laravel Notifications + events/queues for new tickets, replies, assignments, SLA approaching / breach.
- **Audit trail** — a `TicketActivity` model (actor, action, timestamp, before/after values).
- **Attachments** — private object storage, validation, virus scanning, signed URLs.
- **Operations** — CI/CD, staging, monitoring, error tracking, structured logging, database backups, health checks, security hardening.

## Testing

The suite focuses on business risks and security boundaries:

- Organization isolation (clients can only see their own tickets, direct URLs are blocked)
- Internal-note boundary (client responses never contain internal notes; only agents can create them)
- SLA deadline calculation for every priority, plus time-travel tests for `on_track` / `due_soon` / `overdue` and resolved/closed lifecycle
- Agent lifecycle: status, priority, assignment, replies, internal notes
- Validation rules and flash feedback
- Seeder: demo scenarios, internal-note authorship, and pagination-friendly dataset

Run everything:

```bash
php artisan test            # Pest test suite
composer ci:check           # Pint + PHPStan + frontend + tests
```

Frontend checks:

```bash
npm run build
npm run types:check
npm run lint:check
npm run format:check
```

CI (`.github/workflows/tests.yml`) runs `composer setup` + `composer ci:check` on PHP 8.4 / Node 22.

## Installation

Requirements: PHP 8.4, Composer, Node.js 22, a MySQL/MariaDB server (Laravel Herd covers the PHP/Node parts).

```bash
# 1. Copy the environment file and configure the database
cp .env.example .env
#    .env.example ships MySQL/MariaDB defaults (a project requirement).
#    Adjust DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD if your MySQL differs.

# 2. Install PHP + JS dependencies and scaffold
composer setup            # installs deps, key:generate, migrates against your .env DB

# 3. Seed the demo dataset
php artisan migrate:fresh --seed

# 4. Start the app
composer run dev          # or: php artisan serve + npm run dev
```

> Note: TypeScript route helpers (`resources/js/routes/**`) are auto-generated by the Wayfinder Vite plugin whenever the dev server runs or assets are built — no manual step needed after editing routes.

Build assets for production:

```bash
composer install --no-dev --optimize-autoloader
npm run build
```

## Demo credentials

All seeded users share the password `password`.

**Acme BV**

| Name   | Email              | Role  |
| ------ | ------------------ | ----- |
| Alice A| `client1@acme.test`| Client|
| Bob B  | `client2@acme.test`| Client|

**Globex Corp**

| Name | Email              | Role  |
| ---- | ------------------ | ----- |
| Carl C| `client@globex.test`| Client|

**Support**

| Name | Email                | Role |
| ---- | -------------------- | ---- |
| Dan D| `agent1@support.test`| Agent|
| Eve E| `agent2@support.test`| Agent|

The seeder provides 21 tickets (17 for Acme, 4 for Globex) spanning all SLA states, priorities, statuses, and assigned/unassigned tickets. Use **Alice A** to see client-side pagination (page 2 has 2 rows) and an agent account to see all 21 tickets (2 pages). Two tickets include internal notes that only agents can see.