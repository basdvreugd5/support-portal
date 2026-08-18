<?php

namespace App\Models;

use App\Enums\SlaStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $created_by_id
 * @property int|null $assigned_to_id
 * @property string $title
 * @property string $description
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property Carbon $sla_due_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read User $createdBy
 * @property-read User|null $assignedTo
 * @property-read Collection<int, TicketMessage> $messages
 */
#[Fillable([
    'organization_id',
    'created_by_id',
    'assigned_to_id',
    'title',
    'description',
    'status',
    'priority',
    'sla_due_at',
])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Minutes before the deadline a ticket is considered "due soon".
     */
    public const DUE_SOON_WINDOW_MINUTES = 120;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'sla_due_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class);
    }

    /**
     * Restrict a query to the tickets a user is allowed to see.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->role !== UserRole::Agent) {
            $query->where('organization_id', $user->organization_id);
        }
    }

    /**
     * Restrict a query to active tickets past their SLA deadline.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query
            ->whereNotIn('status', $this->inactiveStatuses())
            ->where('sla_due_at', '<=', now());
    }

    /**
     * Restrict a query to active tickets within the due-soon window before
     * their SLA deadline.
     *
     * @param  Builder<Ticket>  $query
     */
    #[Scope]
    protected function dueSoon(Builder $query): void
    {
        $query
            ->whereNotIn('status', $this->inactiveStatuses())
            ->where('sla_due_at', '>', now())
            ->where('sla_due_at', '<=', now()->addMinutes(self::DUE_SOON_WINDOW_MINUTES));
    }

    /**
     * Determine the SLA status derived from the deadline and lifecycle.
     */
    public function slaStatus(): ?SlaStatus
    {
        if (in_array($this->status->value, $this->inactiveStatuses(), true)) {
            return null;
        }

        $now = now();

        if ($now->greaterThanOrEqualTo($this->sla_due_at)) {
            return SlaStatus::Overdue;
        }

        if ($now->addMinutes(self::DUE_SOON_WINDOW_MINUTES)->greaterThanOrEqualTo($this->sla_due_at)) {
            return SlaStatus::DueSoon;
        }

        return SlaStatus::OnTrack;
    }

    /**
     * Statuses that end the SLA lifecycle.
     *
     * @return array{0: string, 1: string}
     */
    private function inactiveStatuses(): array
    {
        return [
            TicketStatus::Resolved->value,
            TicketStatus::Closed->value,
        ];
    }
}
