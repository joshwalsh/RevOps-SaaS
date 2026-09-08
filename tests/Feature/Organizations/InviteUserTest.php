<?php

use App\Enums\OrganizationRole;
use App\Mail\OrganizationInvitationMail;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

it('lets an owner invite a new user and sends the invitation mail', function () {
    Mail::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    Volt::actingAs($owner)
        ->test('pages.organizations.users', ['organization' => $organization])
        ->set('inviteEmail', 'new-member@example.com')
        ->set('inviteRole', OrganizationRole::User->value)
        ->call('invite')
        ->assertHasNoErrors();

    expect(OrganizationInvitation::where('organization_id', $organization->id)
        ->where('email', 'new-member@example.com')
        ->exists())->toBeTrue();

    Mail::assertSent(OrganizationInvitationMail::class);
});

it('lets an admin invite a user but not grant the owner role', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->users()->attach($admin, ['role' => OrganizationRole::Admin]);

    Volt::actingAs($admin)
        ->test('pages.organizations.users', ['organization' => $organization])
        ->set('inviteEmail', 'new-owner@example.com')
        ->set('inviteRole', OrganizationRole::Owner->value)
        ->call('invite')
        ->assertForbidden();
});

it('does not let a plain user invite anyone', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    Volt::actingAs($member)
        ->test('pages.organizations.users', ['organization' => $organization])
        ->set('inviteEmail', 'someone@example.com')
        ->set('inviteRole', OrganizationRole::User->value)
        ->call('invite')
        ->assertForbidden();
});

it('does not let a non-member load the users page for the super-admin organization', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $outsider = User::factory()->create();

    Volt::actingAs($outsider)
        ->test('pages.organizations.users', ['organization' => $superAdminOrg])
        ->assertForbidden();
});

it('rejects a duplicate pending invitation for the same email', function () {
    Mail::fake();

    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'dupe@example.com',
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.users', ['organization' => $organization])
        ->set('inviteEmail', 'dupe@example.com')
        ->set('inviteRole', OrganizationRole::User->value)
        ->call('invite')
        ->assertHasErrors(['inviteEmail']);
});
