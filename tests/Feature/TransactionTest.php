<?php

use App\Models\Organization;
use App\Models\Person;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Database\QueryException;

it('records a paid signup with the person, product, cost, and time', function () {
    $organization = Organization::factory()->create();
    $person = Person::factory()->create();
    $product = Product::factory()->create([
        'organization_id' => $organization->id,
        'price_cents' => 2900,
    ]);

    $transaction = Transaction::query()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
        'product_id' => $product->id,
        'amount_cents' => $product->price_cents,
        'currency' => $product->currency,
    ]);

    expect($transaction->fresh())
        ->person_id->toBe($person->id)
        ->product_id->toBe($product->id)
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
    ]);

    expect($product->isFree())->toBeTrue()
        ->and($transaction->isFree())->toBeTrue()
        ->and($transaction->amount_cents)->toBe(0);
});

it('keeps the transaction amount even if the product price later changes', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id, 'price_cents' => 900]);

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'product_id' => $product->id,
        'amount_cents' => 900,
    ]);

    $product->update(['price_cents' => 1900]);

    expect($transaction->fresh()->amount_cents)->toBe(900);
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

it('prevents deleting a product that has recorded transactions', function () {
    $organization = Organization::factory()->create();
    $product = Product::factory()->create(['organization_id' => $organization->id]);
    Transaction::factory()->create(['organization_id' => $organization->id, 'product_id' => $product->id]);

    expect(fn () => $product->delete())->toThrow(QueryException::class);
});
