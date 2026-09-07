<?php

use App\Models\AnonIdentity;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Person;
use App\Models\TenantPeople;

it('resolves an anonymous identity to a canonical person and backfills events', function () {
    $organization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);
    $event = Event::factory()->create([
        'organization_id' => $organization->id,
        'anon_identity_id' => $identity->id,
        'person_id' => null,
    ]);

    $response = $this->postJson('/api/identity/resolve', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
        'email' => 'Visitor@Example.com ',
    ]);

    $response->assertOk()->assertJson(['anon_id' => $identity->id]);

    $personId = $response->json('person_id');
    expect($personId)->not->toBeNull();

    $person = Person::findOrFail($personId);
    expect($person->email_hash)->toBe(hash('sha256', 'visitor@example.com'));

    $this->assertDatabaseHas('tenant_people', [
        'organization_id' => $organization->id,
        'person_id' => $person->id,
    ]);

    expect($identity->fresh()->person_id)->toBe($person->id)
        ->and($event->fresh()->person_id)->toBe($person->id);
});

it('is idempotent when resolving the same identity and email twice', function () {
    $organization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);

    $payload = [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
        'email' => 'repeat@example.com',
    ];

    $first = $this->postJson('/api/identity/resolve', $payload)->assertOk();
    $second = $this->postJson('/api/identity/resolve', $payload)->assertOk();

    expect($first->json('person_id'))->toBe($second->json('person_id'))
        ->and(Person::query()->count())->toBe(1)
        ->and(TenantPeople::query()->count())->toBe(1);
});

it('reuses the same person across tenants for the same email without leaking the tenant link', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $identityA = AnonIdentity::factory()->create(['organization_id' => $organizationA->id]);
    $identityB = AnonIdentity::factory()->create(['organization_id' => $organizationB->id]);

    $responseA = $this->postJson('/api/identity/resolve', [
        'tenant_id' => $organizationA->id,
        'anon_id' => $identityA->id,
        'email' => 'shared@example.com',
    ])->assertOk();

    $responseB = $this->postJson('/api/identity/resolve', [
        'tenant_id' => $organizationB->id,
        'anon_id' => $identityB->id,
        'email' => 'shared@example.com',
    ])->assertOk();

    expect($responseA->json('person_id'))->toBe($responseB->json('person_id'));

    $this->assertDatabaseHas('tenant_people', ['organization_id' => $organizationA->id]);
    $this->assertDatabaseHas('tenant_people', ['organization_id' => $organizationB->id]);
    expect(TenantPeople::query()->count())->toBe(2);
});

it('rejects an anon_id that does not belong to the given tenant', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $otherOrganization->id]);

    $this->postJson('/api/identity/resolve', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
        'email' => 'someone@example.com',
    ])->assertUnprocessable();

    expect($identity->fresh()->person_id)->toBeNull();
});

it('never accepts or returns a raw email', function () {
    $organization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);

    $response = $this->postJson('/api/identity/resolve', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
        'email' => 'private@example.com',
    ])->assertOk();

    expect($response->json())->not->toHaveKey('email')
        ->and(json_encode($response->json()))->not->toContain('private@example.com');
});
