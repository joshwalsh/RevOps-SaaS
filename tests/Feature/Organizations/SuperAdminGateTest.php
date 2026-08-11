<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('grants a super-admin org member every organization ability, even on organizations they do not belong to', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $superAdminOrg->users()->attach($user, ['role' => OrganizationRole::Member]);

    $otherOrg = Organization::factory()->create();

    expect($user->can('view', $otherOrg))->toBeTrue()
        ->and($user->can('update', $otherOrg))->toBeTrue()
        ->and($user->can('delete', $otherOrg))->toBeTrue();
});

it('does not grant a regular organization member any special powers', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $organization->users()->attach($user, ['role' => OrganizationRole::Member]);

    $otherOrg = Organization::factory()->create();

    expect($user->can('view', $otherOrg))->toBeFalse()
        ->and($user->can('update', $otherOrg))->toBeFalse();
});

it('grants super-admin powers regardless of which organization is currently active', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $superAdminOrg->users()->attach($user, ['role' => OrganizationRole::Member]);

    // The user's default factory organization (not the super-admin org) is current.
    expect($user->currentOrganization->is_super_admin)->toBeFalse();

    $otherOrg = Organization::factory()->create();

    expect($user->can('delete', $otherOrg))->toBeTrue();
});

it('super-admin org membership is granted regardless of role within that organization', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $member = User::factory()->create();
    $superAdminOrg->users()->attach($member, ['role' => OrganizationRole::Member]);

    expect($member->isSuperAdmin())->toBeTrue();
});
