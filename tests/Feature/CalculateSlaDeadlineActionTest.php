<?php

use App\Actions\CalculateSlaDeadlineAction;
use App\Enums\TicketPriority;

it('calculates a 24 hour deadline for high priority tickets', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $deadline = (new CalculateSlaDeadlineAction)->handle(TicketPriority::High);

    expect($deadline->toDateTimeString())->toBe('2026-01-02 09:00:00');
});

it('calculates a 48 hour deadline for normal priority tickets', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $deadline = (new CalculateSlaDeadlineAction)->handle(TicketPriority::Normal);

    expect($deadline->toDateTimeString())->toBe('2026-01-03 09:00:00');
});

it('calculates a 72 hour deadline for low priority tickets', function () {
    $this->travelTo('2026-01-01 09:00:00');

    $deadline = (new CalculateSlaDeadlineAction)->handle(TicketPriority::Low);

    expect($deadline->toDateTimeString())->toBe('2026-01-04 09:00:00');
});
