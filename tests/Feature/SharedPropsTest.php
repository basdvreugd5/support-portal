<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shares a limited user resource with the authenticated dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', $user->name)
            ->where('auth.user.email', $user->email)
            ->where('auth.user.role', ['value' => 'client', 'label' => 'Client User'])
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token'));
});

it('shares empty flash props by default', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.success', null)
            ->where('flash.error', null)
            ->where('flash.toast', null));
});

it('shares flash success from the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Ticket aangemaakt.'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('flash.success', 'Ticket aangemaakt.'));
});

it('shares a null authenticated user for guests', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});
