<?php

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a client to view a ticket from their own organization', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($client->can('view', $ticket))->toBeTrue();
});

it('blocks a client from viewing a ticket from another organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $client = User::factory()->forOrganization($ownOrg)->create();
    $ticket = Ticket::factory()->forOrganization($otherOrg)->create();

    expect($client->can('view', $ticket))->toBeFalse();
});

it('blocks a client without an organization from viewing any ticket', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->create(['organization_id' => null]);
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($client->can('view', $ticket))->toBeFalse();
});

it('blocks a client without an organization from replying to any ticket', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->create(['organization_id' => null]);
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($client->can('reply', $ticket))->toBeFalse();
});

it('allows an agent to view any ticket', function () {
    $organization = Organization::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($agent->can('view', $ticket))->toBeTrue();
});

it('allows a client with an organization to create a ticket', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();

    expect($client->can('create', Ticket::class))->toBeTrue();
});

it('blocks a client without an organization from creating a ticket', function () {
    $client = User::factory()->create(['organization_id' => null]);

    expect($client->can('create', Ticket::class))->toBeFalse();
});

it('allows an agent to create a ticket', function () {
    $agent = User::factory()->agent()->create();

    expect($agent->can('create', Ticket::class))->toBeTrue();
});

it('allows a client to reply to a ticket from their own organization', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($client->can('reply', $ticket))->toBeTrue();
});

it('blocks a client from replying to a ticket from another organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $client = User::factory()->forOrganization($ownOrg)->create();
    $ticket = Ticket::factory()->forOrganization($otherOrg)->create();

    expect($client->can('reply', $ticket))->toBeFalse();
});

it('allows an agent to reply to any ticket', function () {
    $organization = Organization::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($agent->can('reply', $ticket))->toBeTrue();
});

it('blocks clients from updating tickets', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($client->can('update', $ticket))->toBeFalse();
});

it('allows an agent to update any ticket', function () {
    $organization = Organization::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($agent->can('update', $ticket))->toBeTrue();
});

it('blocks clients from assigning tickets', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($client->can('assign', $ticket))->toBeFalse();
});

it('allows an agent to assign any ticket', function () {
    $organization = Organization::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($agent->can('assign', $ticket))->toBeTrue();
});

it('blocks clients from adding internal notes', function () {
    $organization = Organization::factory()->create();
    $client = User::factory()->forOrganization($organization)->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($client->can('addInternalNote', $ticket))->toBeFalse();
});

it('allows an agent to add internal notes', function () {
    $organization = Organization::factory()->create();
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->forOrganization($organization)->create();

    expect($agent->can('addInternalNote', $ticket))->toBeTrue();
});
