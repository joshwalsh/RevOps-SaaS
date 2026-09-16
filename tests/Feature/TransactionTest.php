<?php

use App\Models\Organization;
use App\Models\Person;
use App\Models\Product;
use App\Models\Transaction;

it('records a paid signup with the person, product, cost, and time', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'name' => 'Pro Plan',
        'price_cents' => 2900,
    ]);

    $transaction = Transaction::query()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'amount_cents' => $product->price_cents,
        'currency' => $product->currency,
    ]);

    expect($transaction->fresh())
        ->person_id->toBe($person->id)
        ->product_id->toBe($product->id)
        ->product_name->toBe('Pro Plan')
        ->amount_cents->toBe(2900)
        ->isFree()->toBeFalse()
        ->created_at->not->toBeNull();

    expect($transaction->person->is($person))->toBeTrue()
        ->and($transaction->product->is($product))->toBeTrue()
        ->and($product->fresh()->transactions)->toHaveCount(1)
        ->and($person->fresh()->transactions)->toHaveCount(1);
});

it('records a free signup', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->free()->create(['organization_id' => $organization->id]);

    $transaction = Transaction::factory()->free()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
    ]);

    expect($product->isFree())->toBeTrue()
        ->and($transaction->isFree())->toBeTrue()
        ->and($transaction->amount_cents)->toBe(0);
});

it('records the product name even when it cannot be matched to a product record', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();

    $transaction = Transaction::factory()->withoutMatchedProduct()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
        'product_name' => 'Legacy Starter Plan',
        'amount_cents' => 1500,
    ]);

    expect($transaction->fresh())
        ->product_id->toBeNull()
        ->product_name->toBe('Legacy Starter Plan')
        ->amount_cents->toBe(1500);

    expect($transaction->product)->toBeNull();
});

it('keeps the transaction amount even if the product price later changes', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id, 'price_cents' => 900]);

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'amount_cents' => 900,
    ]);

    $product->update(['price_cents' => 1900]);

    expect($transaction->fresh()->amount_cents)->toBe(900);
});

it('keeps the transaction and its recorded product name when the linked product is deleted', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Deleted Plan']);
    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
    ]);

    $product->delete();

    expect($transaction->fresh())
        ->product_id->toBeNull()
        ->product_name->toBe('Deleted Plan');
});

it('computes amount as total minus fees when amount is not supplied but a total is', function () {
    $organization = Organization::factory()->create();

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'amount_cents' => null,
        'total_cents' => 10000,
        'fees_cents' => 300,
    ]);

    expect($transaction->fresh()->amount_cents)->toBe(9700);
});

it('computes total from subtotal plus tax when no total is supplied, then subtracts fees for the amount', function () {
    $organization = Organization::factory()->create();

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'amount_cents' => null,
        'subtotal_cents' => 10000,
        'tax_cents' => 800,
        'fees_cents' => 300,
    ]);

    expect($transaction->fresh()->amount_cents)->toBe(10500);
});

it('ignores discount when computing amount, since it is already reflected in subtotal', function () {
    $organization = Organization::factory()->create();

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'amount_cents' => null,
        'subtotal_cents' => 10000,
        'discount_cents' => 1000,
        'tax_cents' => 800,
        'fees_cents' => 300,
    ]);

    expect($transaction->fresh()->amount_cents)->toBe(10500);
});

it('absolute-values the computed amount when fees exceed the total, so it never overflows the unsigned column', function () {
    $organization = Organization::factory()->create();

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'amount_cents' => null,
        'total_cents' => 500,
        'fees_cents' => 800,
    ]);

    expect($transaction->fresh()->amount_cents)->toBe(300);
});

it('computes a free amount when no accounting fields are supplied at all', function () {
    $organization = Organization::factory()->create();

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'amount_cents' => null,
    ]);

    expect($transaction->fresh())->amount_cents->toBe(0)->isFree()->toBeTrue();
});

it('does not override an explicitly supplied amount with the breakdown calculation', function () {
    $organization = Organization::factory()->create();

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'amount_cents' => 2900,
        'subtotal_cents' => 10000,
        'fees_cents' => 300,
    ]);

    expect($transaction->fresh()->amount_cents)->toBe(2900);
});

it('scopes transactions and products to a single tenant', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    Transaction::factory()->create(['organization_id' => $organizationA->id]);
    Transaction::factory()->create(['organization_id' => $organizationB->id]);
    Product::factory()->create(['organization_id' => $organizationA->id]);
    Product::factory()->create(['organization_id' => $organizationB->id]);

    expect(Transaction::query()->forOrganization($organizationA)->count())->toBe(1)
        ->and(Product::query()->forOrganization($organizationA)->count())->toBe(1);
});
