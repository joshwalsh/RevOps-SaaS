<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets any member view the product catalog', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Pro Plan']);

    Volt::actingAs($member)
        ->test('pages.organizations.products', ['organization' => $organization])
        ->assertOk()
        ->assertSee('Pro Plan');
});

it('does not let a non-member load the products page', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();

    Volt::actingAs($outsider)
        ->test('pages.organizations.products', ['organization' => $organization])
        ->assertForbidden();
});

it('lets an owner create a product', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    Volt::actingAs($owner)
        ->test('pages.organizations.products', ['organization' => $organization])
        ->set('name', 'Starter Plan')
        ->set('price', '19.00')
        ->set('currency', 'usd')
        ->call('create')
        ->assertHasNoErrors();

    $product = Product::where('organization_id', $organization->id)->firstOrFail();

    expect($product->name)->toBe('Starter Plan')
        ->and($product->price_cents)->toBe(1900)
        ->and($product->currency)->toBe('USD');
});

it('does not let a plain member create a product', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);

    Volt::actingAs($member)
        ->test('pages.organizations.products', ['organization' => $organization])
        ->set('name', 'Starter Plan')
        ->set('price', '19.00')
        ->set('currency', 'USD')
        ->call('create')
        ->assertForbidden();

    expect(Product::where('organization_id', $organization->id)->exists())->toBeFalse();
});

it('lets an admin edit a product', function () {
    $organization = Organization::factory()->create();
    $admin = User::factory()->create();
    $organization->users()->attach($admin, ['role' => OrganizationRole::Admin]);
    $product = Product::factory()->create(['organization_id' => $organization->id, 'price_cents' => 900]);

    Volt::actingAs($admin)
        ->test('pages.organizations.products', ['organization' => $organization])
        ->call('edit', $product->id)
        ->set('editName', 'Renamed Plan')
        ->set('editPrice', '39.00')
        ->set('editCurrency', 'USD')
        ->call('update')
        ->assertHasNoErrors();

    expect($product->fresh())
        ->name->toBe('Renamed Plan')
        ->price_cents->toBe(3900);
});

it('lets an owner delete a product, keeping existing transactions with their recorded product name', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Old Plan']);
    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.products', ['organization' => $organization])
        ->call('delete', $product->id);

    expect(Product::find($product->id))->toBeNull();
    expect($transaction->fresh())
        ->product_id->toBeNull()
        ->product_name->toBe('Old Plan');
});

it('does not let a plain member delete a product', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    $product = Product::factory()->create(['organization_id' => $organization->id]);

    Volt::actingAs($member)
        ->test('pages.organizations.products', ['organization' => $organization])
        ->call('delete', $product->id)
        ->assertForbidden();

    expect(Product::find($product->id))->not->toBeNull();
});
