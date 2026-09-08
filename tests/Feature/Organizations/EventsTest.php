<?php

use App\Enums\OrganizationRole;
use App\Models\AnonIdentity;
use App\Models\CanonicalEvent;
use App\Models\Event;
use App\Models\EventNameMapping;
use App\Models\Organization;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets any member view the raw event names with occurrence counts', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);
    Event::factory()->count(3)->create([
        'organization_id' => $organization->id,
        'anon_identity_id' => $identity->id,
        'event_name' => 'page_view',
    ]);
    Event::factory()->create([
        'organization_id' => $organization->id,
        'anon_identity_id' => $identity->id,
        'event_name' => 'comment',
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->assertOk()
        ->assertSee('page_view')
        ->assertSee('comment');
});

it('does not let a non-member load the events index', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();

    Volt::actingAs($outsider)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->assertForbidden();
});

it('only shows event names recorded for this tenant', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);
    Event::factory()->create([
        'organization_id' => $organization->id,
        'anon_identity_id' => $identity->id,
        'event_name' => 'home_event',
    ]);

    $otherOrganization = Organization::factory()->create();
    $otherIdentity = AnonIdentity::factory()->create(['organization_id' => $otherOrganization->id]);
    Event::factory()->create([
        'organization_id' => $otherOrganization->id,
        'anon_identity_id' => $otherIdentity->id,
        'event_name' => 'other_tenant_event',
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->assertSee('home_event')
        ->assertDontSee('other_tenant_event');
});

it('lets an owner map a raw event name to a new canonical event', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);
    Event::factory()->create([
        'organization_id' => $organization->id,
        'anon_identity_id' => $identity->id,
        'event_name' => 'page_view',
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->call('startMapping', 'page_view')
        ->set('newCanonicalEventName', 'Page View')
        ->call('saveMapping')
        ->assertHasNoErrors();

    $canonicalEvent = CanonicalEvent::where('organization_id', $organization->id)->firstOrFail();
    expect($canonicalEvent->name)->toBe('Page View');

    $mapping = EventNameMapping::where('organization_id', $organization->id)->where('event_name', 'page_view')->firstOrFail();
    expect($mapping->canonical_event_id)->toBe($canonicalEvent->id);
});

it('lets an owner map a second raw name to the same existing canonical event', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);
    Event::factory()->create(['organization_id' => $organization->id, 'anon_identity_id' => $identity->id, 'event_name' => 'page_view']);
    Event::factory()->create(['organization_id' => $organization->id, 'anon_identity_id' => $identity->id, 'event_name' => 'page_render']);

    $canonicalEvent = CanonicalEvent::factory()->create(['organization_id' => $organization->id, 'name' => 'Page View']);
    EventNameMapping::factory()->create([
        'organization_id' => $organization->id,
        'event_name' => 'page_view',
        'canonical_event_id' => $canonicalEvent->id,
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->call('startMapping', 'page_render')
        ->set('selectedCanonicalEventId', (string) $canonicalEvent->id)
        ->call('saveMapping')
        ->assertHasNoErrors();

    expect(CanonicalEvent::where('organization_id', $organization->id)->count())->toBe(1);

    $mapping = EventNameMapping::where('organization_id', $organization->id)->where('event_name', 'page_render')->firstOrFail();
    expect($mapping->canonical_event_id)->toBe($canonicalEvent->id);
});

it('lets an owner remap an event name to a different canonical event', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $original = CanonicalEvent::factory()->create(['organization_id' => $organization->id, 'name' => 'Original']);
    $replacement = CanonicalEvent::factory()->create(['organization_id' => $organization->id, 'name' => 'Replacement']);
    EventNameMapping::factory()->create([
        'organization_id' => $organization->id,
        'event_name' => 'some_event',
        'canonical_event_id' => $original->id,
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->call('startMapping', 'some_event')
        ->set('selectedCanonicalEventId', (string) $replacement->id)
        ->call('saveMapping')
        ->assertHasNoErrors();

    $mapping = EventNameMapping::where('organization_id', $organization->id)->where('event_name', 'some_event')->firstOrFail();
    expect($mapping->canonical_event_id)->toBe($replacement->id);
});

it('lets an owner unmap an event name', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $canonicalEvent = CanonicalEvent::factory()->create(['organization_id' => $organization->id]);
    EventNameMapping::factory()->create([
        'organization_id' => $organization->id,
        'event_name' => 'some_event',
        'canonical_event_id' => $canonicalEvent->id,
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->call('unmap', 'some_event');

    expect(EventNameMapping::where('organization_id', $organization->id)->where('event_name', 'some_event')->exists())->toBeFalse();
    expect(CanonicalEvent::find($canonicalEvent->id))->not->toBeNull();
});

it('does not let a plain member map an event name', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);

    Volt::actingAs($member)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->call('startMapping', 'page_view')
        ->set('newCanonicalEventName', 'Page View')
        ->call('saveMapping')
        ->assertForbidden();

    expect(CanonicalEvent::where('organization_id', $organization->id)->exists())->toBeFalse();
});

it('does not let a plain member unmap an event name', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    $canonicalEvent = CanonicalEvent::factory()->create(['organization_id' => $organization->id]);
    EventNameMapping::factory()->create([
        'organization_id' => $organization->id,
        'event_name' => 'some_event',
        'canonical_event_id' => $canonicalEvent->id,
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->call('unmap', 'some_event')
        ->assertForbidden();

    expect(EventNameMapping::where('organization_id', $organization->id)->where('event_name', 'some_event')->exists())->toBeTrue();
});

it('shows canonical events grouped with the raw names that roll up into them', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);
    Event::factory()->create(['organization_id' => $organization->id, 'anon_identity_id' => $identity->id, 'event_name' => 'page_view']);
    Event::factory()->create(['organization_id' => $organization->id, 'anon_identity_id' => $identity->id, 'event_name' => 'page_render']);

    $canonicalEvent = CanonicalEvent::factory()->create(['organization_id' => $organization->id, 'name' => 'Page View']);
    EventNameMapping::factory()->create(['organization_id' => $organization->id, 'event_name' => 'page_view', 'canonical_event_id' => $canonicalEvent->id]);
    EventNameMapping::factory()->create(['organization_id' => $organization->id, 'event_name' => 'page_render', 'canonical_event_id' => $canonicalEvent->id]);

    Volt::actingAs($member)
        ->test('pages.organizations.events', ['organization' => $organization])
        ->assertSeeInOrder(['Page View', 'page_view', 'page_render']);
});
