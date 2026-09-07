<?php

use App\Models\AnonIdentity;
use App\Models\Organization;

it('creates a new anonymous identity when the caller has none yet', function () {
    $organization = Organization::factory()->create();

    $response = $this->postJson('/api/identity/touch', [
        'tenant_id' => $organization->id,
        'anon_id' => null,
    ]);

    $response->assertOk()->assertJson(['person_id' => null]);

    $anonId = $response->json('anon_id');

    expect($anonId)->not->toBeNull();
    $this->assertDatabaseHas('anon_identities', [
        'id' => $anonId,
        'organization_id' => $organization->id,
    ]);
});

it('returns the same identity unchanged when it already exists for the tenant', function () {
    $organization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $organization->id]);

    $response = $this->postJson('/api/identity/touch', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
    ]);

    $response->assertOk()->assertJson([
        'anon_id' => $identity->id,
        'person_id' => null,
    ]);

    expect(AnonIdentity::query()->count())->toBe(1);
});

it('mints a new identity when the anon_id does not belong to this tenant', function () {
    $otherOrganization = Organization::factory()->create();
    $identity = AnonIdentity::factory()->create(['organization_id' => $otherOrganization->id]);

    $organization = Organization::factory()->create();

    $response = $this->postJson('/api/identity/touch', [
        'tenant_id' => $organization->id,
        'anon_id' => $identity->id,
    ]);

    $response->assertOk();

    expect($response->json('anon_id'))->not->toBe($identity->id);
    $this->assertDatabaseHas('anon_identities', [
        'id' => $identity->id,
        'organization_id' => $otherOrganization->id,
    ]);
});

it('rejects an unknown tenant_id', function () {
    $this->postJson('/api/identity/touch', [
        'tenant_id' => 999999,
        'anon_id' => null,
    ])->assertUnprocessable();
});
