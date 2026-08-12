<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

it('creates the super-admin organization on first run and attaches the user as owner', function () {
    $user = User::factory()->create();

    $this->artisan('organizations:promote-super-admin', ['email' => $user->email])
        ->assertSuccessful();

    expect(Organization::query()->where('is_super_admin', true)->count())->toBe(1);

    $user->refresh();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->hasRole(Organization::query()->where('is_super_admin', true)->firstOrFail(), OrganizationRole::Owner))->toBeTrue();
});

it('reuses the existing super-admin organization instead of creating a second one', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->artisan('organizations:promote-super-admin', ['email' => $first->email])->assertSuccessful();
    $this->artisan('organizations:promote-super-admin', ['email' => $second->email])->assertSuccessful();

    expect(Organization::query()->where('is_super_admin', true)->count())->toBe(1);
});

it('is idempotent when promoting the same user twice', function () {
    $user = User::factory()->create();

    $this->artisan('organizations:promote-super-admin', ['email' => $user->email])->assertSuccessful();
    $this->artisan('organizations:promote-super-admin', ['email' => $user->email])->assertSuccessful();

    $organization = Organization::query()->where('is_super_admin', true)->firstOrFail();

    expect($organization->users()->count())->toBe(1);
});

it('sets the current organization when the user had none', function () {
    $user = User::factory()->create();
    $user->forceFill(['current_organization_id' => null])->save();

    $this->artisan('organizations:promote-super-admin', ['email' => $user->email])->assertSuccessful();

    $user->refresh();
    expect($user->current_organization_id)->not->toBeNull()
        ->and($user->currentOrganization->is_super_admin)->toBeTrue();
});

it('fails gracefully for an unknown email', function () {
    $this->artisan('organizations:promote-super-admin', ['email' => 'nobody@example.com'])
        ->assertFailed();
});
