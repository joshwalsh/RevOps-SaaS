<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('allows a super admin to view the organizations index', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $user = User::factory()->create();
    $superAdminOrg->users()->attach($user, ['role' => OrganizationRole::Member]);

    $otherOrg = Organization::factory()->create(['name' => 'Acme Inc']);

    $this->actingAs($user)
        ->get(route('admin.organizations'))
        ->assertOk()
        ->assertSee('Acme Inc')
        ->assertSee($superAdminOrg->name);
});

it('forbids a regular user from viewing the organizations index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.organizations'))
        ->assertForbidden();
});
