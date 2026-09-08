<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('allows a super admin to view the platform-wide users index', function () {
    $superAdminOrg = Organization::factory()->superAdmin()->create();
    $admin = User::factory()->create();
    $superAdminOrg->users()->attach($admin, ['role' => OrganizationRole::User]);

    $otherOrg = Organization::factory()->create();
    $otherMember = User::factory()->create(['name' => 'Jane Doe']);
    $otherOrg->users()->attach($otherMember, ['role' => OrganizationRole::Owner]);

    $this->actingAs($admin)
        ->get(route('admin.users'))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee($otherOrg->name);
});

it('forbids a regular user from viewing the platform-wide users index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.users'))
        ->assertForbidden();
});
