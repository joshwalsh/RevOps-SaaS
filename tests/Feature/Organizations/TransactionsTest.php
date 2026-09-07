<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Product;
use App\Models\TenantPeople;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets any member view the transactions list', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);
    Transaction::factory()->create(['organization_id' => $organization->id, 'product_name' => 'Pro Plan']);

    Volt::actingAs($member)
        ->test('pages.organizations.transactions', ['organization' => $organization])
        ->assertOk()
        ->assertSee('Pro Plan');
});

it('does not let a non-member load the transactions page', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();

    Volt::actingAs($outsider)
        ->test('pages.organizations.transactions', ['organization' => $organization])
        ->assertForbidden();
});

it('lets an owner record a signup for an existing person and product', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $person = Person::factory()->create();
    $tenantPerson = TenantPeople::factory()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
        'first_name' => 'Ada',
    ]);
    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Pro Plan', 'price_cents' => 2900]);

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions', ['organization' => $organization])
        ->set('personMode', 'existing')
        ->set('personId', $tenantPerson->person_id)
        ->set('productMode', 'existing')
        ->set('productId', (string) $product->id)
        ->set('amount', '29.00')
        ->set('currency', 'usd')
        ->call('record')
        ->assertHasNoErrors();

    $transaction = Transaction::where('organization_id', $organization->id)->firstOrFail();

    expect($transaction->person_id)->toBe($person->id)
        ->and($transaction->product_id)->toBe($product->id)
        ->and($transaction->product_name)->toBe('Pro Plan')
        ->and($transaction->amount_cents)->toBe(2900)
        ->and($transaction->currency)->toBe('USD');
});

it('lets an owner record a signup for a brand new contact with no matching product', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions', ['organization' => $organization])
        ->set('personMode', 'new')
        ->set('newFirstName', 'Grace')
        ->set('newLastName', 'Hopper')
        ->set('newEmail', 'grace@example.com')
        ->set('productMode', 'other')
        ->set('productName', 'Legacy Plan')
        ->set('amount', '0')
        ->set('currency', 'usd')
        ->call('record')
        ->assertHasNoErrors();

    $transaction = Transaction::where('organization_id', $organization->id)->firstOrFail();

    expect($transaction->product_id)->toBeNull()
        ->and($transaction->product_name)->toBe('Legacy Plan')
        ->and($transaction->isFree())->toBeTrue();

    $person = Person::findOrFail($transaction->person_id);
    expect($person->email_hash)->toBe(hash('sha256', 'grace@example.com'));

    $tenantPerson = TenantPeople::where('organization_id', $organization->id)->where('person_id', $person->id)->firstOrFail();
    expect($tenantPerson->fullName())->toBe('Grace Hopper')
        ->and($tenantPerson->email)->toBe('grace@example.com');
});

it('does not let a plain member record a transaction', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::Member]);

    Volt::actingAs($member)
        ->test('pages.organizations.transactions', ['organization' => $organization])
        ->set('personMode', 'new')
        ->set('productMode', 'other')
        ->set('productName', 'Anything')
        ->set('amount', '0')
        ->set('currency', 'USD')
        ->call('record')
        ->assertForbidden();

    expect(Transaction::where('organization_id', $organization->id)->exists())->toBeFalse();
});

it('rejects an existing-person selection that belongs to a different tenant', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $otherOrganization = Organization::factory()->create();
    $otherTenantPerson = TenantPeople::factory()->create(['organization_id' => $otherOrganization->id]);

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions', ['organization' => $organization])
        ->set('personMode', 'existing')
        ->set('personId', $otherTenantPerson->person_id)
        ->set('productMode', 'other')
        ->set('productName', 'Anything')
        ->set('amount', '0')
        ->set('currency', 'USD')
        ->call('record')
        ->assertHasErrors(['personId']);
});

it('rejects an existing-product selection that belongs to a different tenant', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $otherOrganization = Organization::factory()->create();
    $otherProduct = Product::factory()->create(['organization_id' => $otherOrganization->id]);

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions', ['organization' => $organization])
        ->set('personMode', 'new')
        ->set('newEmail', 'someone@example.com')
        ->set('productMode', 'existing')
        ->set('productId', (string) $otherProduct->id)
        ->set('amount', '0')
        ->set('currency', 'USD')
        ->call('record')
        ->assertHasErrors(['productId']);
});
