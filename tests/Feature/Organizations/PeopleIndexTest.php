<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\TenantPeople;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets any member view the people list', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    TenantPeople::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.people.index', ['organization' => $organization])
        ->assertOk()
        ->assertSee('Ada Lovelace');
});

it('does not let a non-member load the people list', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();

    Volt::actingAs($outsider)
        ->test('pages.organizations.people.index', ['organization' => $organization])
        ->assertForbidden();
});

it('only lists people belonging to this tenant', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    TenantPeople::factory()->create(['organization_id' => $organization->id, 'first_name' => 'Home Tenant']);

    $otherOrganization = Organization::factory()->create();
    TenantPeople::factory()->create(['organization_id' => $otherOrganization->id, 'first_name' => 'Other Tenant']);

    Volt::actingAs($member)
        ->test('pages.organizations.people.index', ['organization' => $organization])
        ->assertSee('Home Tenant')
        ->assertDontSee('Other Tenant');
});

it('filters the list by search', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    TenantPeople::factory()->create(['organization_id' => $organization->id, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);
    TenantPeople::factory()->create(['organization_id' => $organization->id, 'first_name' => 'Grace', 'last_name' => 'Hopper']);

    Volt::actingAs($member)
        ->test('pages.organizations.people.index', ['organization' => $organization])
        ->set('search', 'Hopper')
        ->assertSee('Grace Hopper')
        ->assertDontSee('Ada Lovelace');
});
