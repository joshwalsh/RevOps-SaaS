<?php

use App\Enums\OrganizationRole;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TenantPeople;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets any member view a person detail page with contact info and activity', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);
    $tenantPerson = TenantPeople::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);
    Event::factory()->create(['organization_id' => $organization->id, 'person_id' => $tenantPerson->person_id, 'event_name' => 'page_view']);
    Transaction::factory()->create(['organization_id' => $organization->id, 'person_id' => $tenantPerson->person_id, 'product_name' => 'Pro Plan']);

    Volt::actingAs($member)
        ->test('pages.organizations.people.show', ['organization' => $organization, 'tenantPersonId' => $tenantPerson->id])
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('ada@example.com')
        ->assertSee('Pro Plan');
});

it('404s when the tenant person id belongs to a different tenant', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);
    $member->switchOrganization($organization);

    $otherOrganization = Organization::factory()->create();
    $otherTenantPerson = TenantPeople::factory()->create(['organization_id' => $otherOrganization->id]);

    $this->actingAs($member)
        ->get(route('organizations.people.show', [$organization, $otherTenantPerson->id]))
        ->assertNotFound();
});

it('forbids a non-member from viewing a person in this tenant', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();
    $tenantPerson = TenantPeople::factory()->create(['organization_id' => $organization->id]);

    Volt::actingAs($outsider)
        ->test('pages.organizations.people.show', ['organization' => $organization, 'tenantPersonId' => $tenantPerson->id])
        ->assertForbidden();
});

it('lets an owner edit the captured contact info', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $tenantPerson = TenantPeople::factory()->create(['organization_id' => $organization->id, 'first_name' => 'Old']);

    Volt::actingAs($owner)
        ->test('pages.organizations.people.show', ['organization' => $organization, 'tenantPersonId' => $tenantPerson->id])
        ->set('firstName', 'New')
        ->set('lastName', 'Name')
        ->set('email', 'new@example.com')
        ->call('updateContact')
        ->assertHasNoErrors();

    expect($tenantPerson->fresh())
        ->first_name->toBe('New')
        ->last_name->toBe('Name')
        ->email->toBe('new@example.com');
});

it('does not let a plain member edit contact info', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);
    $tenantPerson = TenantPeople::factory()->create(['organization_id' => $organization->id, 'first_name' => 'Old']);

    Volt::actingAs($member)
        ->test('pages.organizations.people.show', ['organization' => $organization, 'tenantPersonId' => $tenantPerson->id])
        ->set('firstName', 'New')
        ->call('updateContact')
        ->assertForbidden();

    expect($tenantPerson->fresh()->first_name)->toBe('Old');
});
