<?php

use App\Models\AnonIdentity;
use App\Models\Organization;
use App\Models\Person;

it('logs an event for an unresolved anonymous identity', function () {
    $organization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);

    $response = $this->postJson('/api/identity/event', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
        'event_name' => 'page_view',
        'properties' => ['path' => '/pricing'],
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    $this->assertDatabaseHas('events', [
        'organization_id' => $organization->id,
        'anon_identity_id' => $identity->id,
        'person_id' => null,
        'event_name' => 'page_view',
    ]);
});

it('attributes the event to the resolved person when the identity is known', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();
    $identity = AnonIdentity::factory()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
    ]);

    $this->postJson('/api/identity/event', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
        'event_name' => 'purchase',
    ])->assertOk();

    $this->assertDatabaseHas('events', [
        'organization_id' => $organization->id,
        'anon_identity_id' => $identity->id,
        'person_id' => $person->id,
        'event_name' => 'purchase',
    ]);
});

it('rejects an anon_id that does not belong to the given tenant', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $otherOrganization->id]);

    $this->postJson('/api/identity/event', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
        'event_name' => 'page_view',
    ])->assertUnprocessable();

    $this->assertDatabaseCount('events', 0);
});

it('requires an event_name', function () {
    $organization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);

    $this->postJson('/api/identity/event', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
    ])->assertUnprocessable();
});
