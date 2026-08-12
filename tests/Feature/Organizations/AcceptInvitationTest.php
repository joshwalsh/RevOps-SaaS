<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Livewire\Volt\Volt;

it('lets a brand new email register and join the organization in one step', function () {
    $organization = Organization::factory()->create();
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'new-person@example.com',
        'role' => OrganizationRole::Member,
    ]);

    Volt::test('pages.organizations.accept-invitation', ['invitation' => $invitation])
        ->set('name', 'New Person')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'new-person@example.com')->firstOrFail();

    expect($user->hasRole($organization, OrganizationRole::Member))->toBeTrue()
        ->and($user->current_organization_id)->toBe($organization->id)
        ->and(OrganizationInvitation::find($invitation->id))->toBeNull();

    $this->assertAuthenticatedAs($user);
});

it('lets an already-authenticated invited user accept directly', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'existing@example.com',
        'role' => OrganizationRole::Admin,
    ]);

    Volt::actingAs($user)
        ->test('pages.organizations.accept-invitation', ['invitation' => $invitation])
        ->call('accept')
        ->assertRedirect(route('dashboard', absolute: false));

    expect($user->hasRole($organization, OrganizationRole::Admin))->toBeTrue()
        ->and(OrganizationInvitation::find($invitation->id))->toBeNull();
});

it('lets an existing but unauthenticated invited user log in and accept', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'password' => Hash::make('correct-password'),
    ]);
    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'existing@example.com',
        'role' => OrganizationRole::Member,
    ]);

    Volt::test('pages.organizations.accept-invitation', ['invitation' => $invitation])
        ->set('loginPassword', 'correct-password')
        ->call('login')
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    expect($user->hasRole($organization, OrganizationRole::Member))->toBeTrue()
        ->and(OrganizationInvitation::find($invitation->id))->toBeNull();
});

it('rejects acceptance when authenticated as a different account than the invited email', function () {
    $organization = Organization::factory()->create();
    User::factory()->create(['email' => 'invited@example.com']);
    $wrongUser = User::factory()->create(['email' => 'someone-else@example.com']);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
    ]);

    Volt::actingAs($wrongUser)
        ->test('pages.organizations.accept-invitation', ['invitation' => $invitation])
        ->call('accept')
        ->assertForbidden();

    expect($wrongUser->hasRole($organization, OrganizationRole::Member))->toBeFalse()
        ->and(OrganizationInvitation::find($invitation->id))->not->toBeNull();
});

it('rejects an invalid or expired signed invitation link', function () {
    $organization = Organization::factory()->create();
    $invitation = OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

    $expiredUrl = URL::temporarySignedRoute(
        'organizations.invitations.accept',
        now()->subDay(),
        ['invitation' => $invitation->id],
    );

    $this->get($expiredUrl)->assertForbidden();
});

it('is idempotent when the invited user is already a member', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['email' => 'already@example.com']);
    $organization->users()->attach($user, ['role' => OrganizationRole::Member]);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'already@example.com',
    ]);

    Volt::actingAs($user)->test('pages.organizations.accept-invitation', ['invitation' => $invitation]);

    expect(OrganizationInvitation::find($invitation->id))->toBeNull();
});
