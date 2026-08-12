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
