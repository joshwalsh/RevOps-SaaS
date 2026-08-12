<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('shows the platform admin nav and badge when the user has switched to the super-admin organization', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $superAdminOrg->users()->attach($user, ['role' => OrganizationRole::Member]);
    $user->switchOrganization($superAdminOrg);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Platform Admin'))
        ->assertSee(route('admin.organizations'))
        ->assertSee(route('admin.members'));
});

it('shows the normal nav for a super admin currently viewing a regular organization', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $superAdminOrg->users()->attach($user, ['role' => OrganizationRole::Member]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('Platform Admin'))
        ->assertDontSee(route('admin.organizations'));
});

it('shows the normal nav for a regular member', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(__('Platform Admin'))
        ->assertDontSee(route('admin.organizations'));
});
