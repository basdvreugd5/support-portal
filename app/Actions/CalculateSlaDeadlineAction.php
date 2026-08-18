<?php

namespace App\Actions;

use App\Enums\TicketPriority;
use Carbon\CarbonInterface;

class CalculateSlaDeadlineAction
{
    /**
     * Calculate the SLA deadline for a ticket of the given priority.
     */
    public function handle(TicketPriority $priority): CarbonInterface
    {
        return now()->addHours($priority->slaHours());
    }
}
