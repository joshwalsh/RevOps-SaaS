<?php

use App\Enums\OrganizationRole;
use App\Enums\TransactionStatus;
use App\Models\Organization;
use App\Models\Product;
use App\Models\TenantPeople;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Volt\Volt;

it('lets any member view a transaction detail page', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Pro Plan']);
    $tenantPerson = TenantPeople::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ]);
    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'person_id' => $tenantPerson->person_id,
        'product_id' => $product->id,
        'product_name' => 'Pro Plan',
        'amount_cents' => 2900,
        'currency' => 'USD',
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.transactions.show', ['organization' => $organization, 'transactionId' => $transaction->id])
        ->assertOk()
        ->assertSee('Pro Plan')
        ->assertSee('Ada Lovelace')
        ->assertSee('$29.00 USD');
});

it('shows the accounting breakdown when present', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    $transaction = Transaction::factory()->create([
        'organization_id' => $organization->id,
        'subtotal_cents' => 3000,
        'tax_cents' => 200,
        'total_cents' => 3200,
        'fees_cents' => 100,
        'discount_cents' => 500,
        'external_id' => 'ch_12345',
        'status' => TransactionStatus::Refund,
    ]);

    Volt::actingAs($member)
        ->test('pages.organizations.transactions.show', ['organization' => $organization, 'transactionId' => $transaction->id])
        ->assertOk()
        ->assertSee('ch_12345')
        ->assertSee('Refunded');
});

it('404s when the transaction id belongs to a different organization', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    $otherOrganization = Organization::factory()->create();
    $otherTransaction = Transaction::factory()->create(['organization_id' => $otherOrganization->id]);

    $this->actingAs($member)
        ->get(route('organizations.transactions.show', [$organization, $otherTransaction->id]))
        ->assertNotFound();
});

it('forbids a non-member from viewing a transaction in this tenant', function () {
    $organization = Organization::factory()->create();
    $outsider = User::factory()->create();
    $transaction = Transaction::factory()->create(['organization_id' => $organization->id]);

    Volt::actingAs($outsider)
        ->test('pages.organizations.transactions.show', ['organization' => $organization, 'transactionId' => $transaction->id])
        ->assertForbidden();
});
