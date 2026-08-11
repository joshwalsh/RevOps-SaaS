<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('allows any member to view their organization', function (OrganizationRole $role) {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => $role]);

    expect($user->can('view', $organization))->toBeTrue();
})->with([OrganizationRole::Owner, OrganizationRole::Admin, OrganizationRole::Member]);

it('denies non-members from viewing or updating an organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect($user->can('view', $organization))->toBeFalse()
        ->and($user->can('update', $organization))->toBeFalse();
});

it('allows owners and admins to update the organization but not plain members', function () {
    $organization = Organization::factory()->create();

    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $admin = User::factory()->create();
    $organization->users()->attach($admin, ['role' => OrganizationRole::Admin]);

    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);

    expect($owner->can('update', $organization))->toBeTrue()
        ->and($admin->can('update', $organization))->toBeTrue()
        ->and($member->can('update', $organization))->toBeFalse();
});

it('only allows owners to delete the organization', function () {
    $organization = Organization::factory()->create();

    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $admin = User::factory()->create();
    $organization->users()->attach($admin, ['role' => OrganizationRole::Admin]);

    expect($owner->can('delete', $organization))->toBeTrue()
        ->and($admin->can('delete', $organization))->toBeFalse();
});

it('never allows deleting the super-admin organization, even for its owner', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $owner = User::factory()->create();
    $superAdminOrg->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    expect($owner->can('delete', $superAdminOrg))->toBeFalse();
});
