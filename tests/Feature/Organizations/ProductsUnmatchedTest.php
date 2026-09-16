<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets any member view distinct unmatched product names with their counts', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Mystery Plan',
    ]);
    Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Mystery Plan',
    ]);
    Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Another Plan',
    ]);
    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Pro Plan']);
    Transaction::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_name' => 'Pro Plan',
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->assertOk()
        ->assertSee('Mystery Plan')
        ->assertSee('Another Plan')
        ->assertDontSee('Pro Plan');
});

it('does not let a non-member load the unmatched products page', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();

    Volt::actingAs($outsider)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->assertForbidden();
});

it('lets a manager match every unmatched transaction sharing a product name to a catalog product', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Pro Plan']);
    $first = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Pro Plan (legacy)',
    ]);
    $second = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Pro Plan (legacy)',
    ]);
    $unrelated = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Other Plan',
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->call('matchStart', 'Pro Plan (legacy)')
        ->set('selectedProductId', (string) $product->id)
        ->call('match')
        ->assertHasNoErrors();

    expect($first->fresh()->product_id)->toBe($product->id)
        ->and($second->fresh()->product_id)->toBe($product->id)
        ->and($unrelated->fresh()->product_id)->toBeNull();
});

it('requires a product to be selected before matching', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $transaction = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Mystery Plan',
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->call('matchStart', 'Mystery Plan')
        ->call('match')
        ->assertHasErrors('selectedProductId');

    expect($transaction->fresh()->product_id)->toBeNull();
});

it('does not let a plain member match products', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    $product = Product::factory()->create(['organization_id' => $organization->id]);
    $transaction = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Mystery Plan',
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->call('matchStart', 'Mystery Plan')
        ->set('selectedProductId', (string) $product->id)
        ->call('match')
        ->assertForbidden();

    expect($transaction->fresh()->product_id)->toBeNull();
});

it('lets a manager create a new product from an unmatched name, prefilled from the most recent transaction', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $older = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'New Plan',
        'amount_cents' => 900,
        'currency' => 'USD',
        'created_at' => now()->subDay(),
    ]);
    $newer = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'New Plan',
        'amount_cents' => 2900,
        'currency' => 'EUR',
        'created_at' => now(),
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->call('createStart', 'New Plan', 2900, 'EUR')
        ->assertSet('newProductName', 'New Plan')
        ->assertSet('newProductPrice', '29.00')
        ->assertSet('newProductCurrency', 'EUR')
        ->call('createProduct')
        ->assertHasNoErrors();

    $product = Product::where('organization_id', $organization->id)->where('name', 'New Plan')->firstOrFail();

    expect($product->price_cents)->toBe(2900)
        ->and($product->currency)->toBe('EUR')
        ->and($older->fresh()->product_id)->toBe($product->id)
        ->and($newer->fresh()->product_id)->toBe($product->id);
});

it('requires product details before creating a product from an unmatched name', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $transaction = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'New Plan',
        'amount_cents' => 900,
        'currency' => 'USD',
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->call('createStart', 'New Plan', 900, 'USD')
        ->set('newProductName', '')
        ->call('createProduct')
        ->assertHasErrors('newProductName');

    expect(Product::where('organization_id', $organization->id)->exists())->toBeFalse();
    expect($transaction->fresh()->product_id)->toBeNull();
});

it('does not let a plain member create a product from the unmatched products page', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);
    $transaction = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'New Plan',
        'amount_cents' => 900,
        'currency' => 'USD',
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->call('createStart', 'New Plan', 900, 'USD')
        ->call('createProduct')
        ->assertForbidden();

    expect(Product::where('organization_id', $organization->id)->exists())->toBeFalse();
    expect($transaction->fresh()->product_id)->toBeNull();
});

it('only matches unmatched transactions within the same organization', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Pro Plan']);
    $ours = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'product_name' => 'Pro Plan',
    ]);

    $otherOrganization = Organization::factory()->create();
    $theirs = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $otherOrganization->id,
        'product_name' => 'Pro Plan',
    ]);

    Volt::actingAs($owner)
        ->test('pages.organizations.products.unmatched', ['organization' => $organization])
        ->call('matchStart', 'Pro Plan')
        ->set('selectedProductId', (string) $product->id)
        ->call('match')
        ->assertHasNoErrors();

    expect($ours->fresh()->product_id)->toBe($product->id)
        ->and($theirs->fresh()->product_id)->toBeNull();
});
