<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('redirects a user with no current organization to create one', function () {
    $user = User::factory()->create();
    $user->organizations()->detach();
    $user->forceFill(['current_organization_id' => null])->save();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertRedirect(route('organizations.create'));
});

it('does not redirect a super admin with no current organization', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $user->organizations()->detach();
    $superAdminOrg->users()->attach($user, ['role' => OrganizationRole::Member]);
    $user->forceFill(['current_organization_id' => null])->save();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});

it('reassigns a stale current organization to a remaining membership', function () {
    $user = User::factory()->create();
    $fallbackOrganizationId = $user->current_organization_id;

    $staleOrganization = Organization::factory()->create();
    $user->forceFill(['current_organization_id' => $staleOrganization->id])->save();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    expect($user->fresh()->current_organization_id)->toBe($fallbackOrganizationId);
});

it('leaves a valid current organization untouched', function () {
    $user = User::factory()->create();
    $organizationId = $user->current_organization_id;

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    expect($user->fresh()->current_organization_id)->toBe($organizationId);
});
