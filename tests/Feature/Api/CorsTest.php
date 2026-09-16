<?php

use App\Models\Organization;

it('allows a cross-origin preflight request to the identity endpoints', function () {
    $response = $this->withHeaders([
        'Origin' => 'https://tenant-site.example',
        'Access-Control-Request-Method' => 'POST',
        'Access-Control-Request-Headers' => 'Content-Type',
    ])->options('/api/identity/touch');

    $response->assertSuccessful()
        ->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeader('Access-Control-Allow-Methods', 'POST');
});

it('adds CORS headers to the actual response from an arbitrary origin', function () {
    $organization = Organization::factory()->create();

    $response = $this->withHeaders(['Origin' => 'https://tenant-site.example'])
        ->postJson('/api/identity/touch', ['tenant_id' => $organization->id, 'anon_id' => null]);

    $response->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
});
