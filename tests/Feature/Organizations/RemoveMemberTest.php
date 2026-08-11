<?php

use App\Actions\Organization\RemoveOrganizationMember;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

it('lets an owner remove a plain member', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);

    (new RemoveOrganizationMember)($owner, $organization, $member);

    expect($organization->fresh()->users()->whereKey($member->id)->exists())->toBeFalse();
});

it('lets an admin remove a plain member but not an owner', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->users()->attach($admin, ['role' => OrganizationRole::Admin]);
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);

    (new RemoveOrganizationMember)($admin, $organization, $member);
    expect($organization->fresh()->users()->whereKey($member->id)->exists())->toBeFalse();

    expect(fn () => (new RemoveOrganizationMember)($admin, $organization, $owner))
        ->toThrow(AuthorizationException::class);
});

it('never allows removing the last remaining owner', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $admin = User::factory()->create();
    $organization->users()->attach($admin, ['role' => OrganizationRole::Admin]);

    expect(fn () => (new RemoveOrganizationMember)($owner, $organization, $owner))
        ->toThrow(AuthorizationException::class);

    expect($organization->fresh()->users()->whereKey($owner->id)->exists())->toBeTrue();
});

it('allows removing an owner when another owner remains', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $secondOwner = User::factory()->create();
    $organization->users()->attach($secondOwner, ['role' => OrganizationRole::Owner]);

    (new RemoveOrganizationMember)($owner, $organization, $secondOwner);

    expect($organization->fresh()->users()->whereKey($secondOwner->id)->exists())->toBeFalse();
});

it('lets a member voluntarily leave the organization', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);

    (new RemoveOrganizationMember)($member, $organization, $member);

    expect($organization->fresh()->users()->whereKey($member->id)->exists())->toBeFalse();
});

it('reassigns the current organization to another membership when removed from the active one', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $member = User::factory()->create();
    // The member's default factory organization remains a fallback membership.
    $fallbackOrganization = $member->currentOrganization;
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    $member->switchOrganization($organization);

    (new RemoveOrganizationMember)($owner, $organization, $member);

    $member->refresh();
    expect($member->current_organization_id)->toBe($fallbackOrganization->id);
});

it('clears the current organization when the user has no memberships left', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $member = User::factory()->create();
    $member->organizations()->detach();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    $member->switchOrganization($organization);

    (new RemoveOrganizationMember)($owner, $organization, $member);

    $member->refresh();
    expect($member->current_organization_id)->toBeNull();
});
