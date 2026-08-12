<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets a user switch to an organization they belong to', function () {
    $user = User::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $otherOrganization->users()->attach($user, ['role' => OrganizationRole::Member]);

    Volt::actingAs($user)
        ->test('layout.organization-switcher')
        ->call('switchTo', $otherOrganization)
        ->assertRedirect(route('dashboard', absolute: false));

    expect($user->fresh()->current_organization_id)->toBe($otherOrganization->id);
});

it('forbids switching to an organization the user does not belong to', function () {
    $user = User::factory()->create();
    $originalOrganizationId = $user->current_organization_id;
    $otherOrganization = Organization::factory()->create();

    Volt::actingAs($user)
        ->test('layout.organization-switcher')
        ->call('switchTo', $otherOrganization)
        ->assertForbidden();

    expect($user->fresh()->current_organization_id)->toBe($originalOrganizationId);
});

it('lists the super-admin organization first in the switcher, ahead of alphabetically earlier organizations', function () {
    $user = User::factory()->create();
    $superAdminOrg = Organization::factory()->superAdmin()->create(['name' => 'Zzz Platform Administration']);
    $superAdminOrg->users()->attach($user, ['role' => OrganizationRole::Member]);

    $organizations = Volt::actingAs($user)
        ->test('layout.organization-switcher')
        ->viewData('organizations');

    expect($organizations->first()->is($superAdminOrg))->toBeTrue();
});
