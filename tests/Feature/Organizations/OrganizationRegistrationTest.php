<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Livewire\Volt\Volt;

it('creates an organization and makes the new user its owner', function () {
    $component = Volt::test('pages.auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('organization_name', 'Acme Inc')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $component->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->firstOrFail();
    $organization = Organization::where('name', 'Acme Inc')->firstOrFail();

    expect($user->hasRole($organization, OrganizationRole::Owner))->toBeTrue()
        ->and($user->current_organization_id)->toBe($organization->id)
        ->and($organization->is_super_admin)->toBeFalse();
});

it('requires an organization name to register', function () {
    $component = Volt::test('pages.auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('organization_name', '')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $component->assertHasErrors(['organization_name' => 'required']);

    $this->assertGuest();
});

it('never lets public registration create a super-admin organization', function () {
    $component = Volt::test('pages.auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('organization_name', 'Acme Inc')
        ->set('password', 'password')
        ->set('password_confirmation', 'password');

    $component->call('register');

    $organization = Organization::where('name', 'Acme Inc')->firstOrFail();

    expect($organization->is_super_admin)->toBeFalse()
        ->and(Organization::query()->where('is_super_admin', true)->count())->toBe(0);
});
