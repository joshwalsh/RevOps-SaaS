<?php

use App\Enums\OrganizationRole;
use App\Enums\TransactionStatus;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Product;
use App\Models\TenantPeople;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('local');
});

function fakeTransactionsCsv(string $name, string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

function ownerOf(Organization $organization): User
{
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    return $owner;
}

it('lets an owner view the import page', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->assertOk();
});

it('forbids a plain member from viewing the import page', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    Volt::actingAs($member)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->assertForbidden();
});

it('shows the detected columns after uploading a csv', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv('transactions.csv', "Email,First Name,Last Name,Product,Amount,Status\nalice@example.com,Alice,Doe,Pro Plan,29.00,paid\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->assertSee('Email')
        ->assertSee('Product')
        ->assertSee('Amount')
        ->assertSee('Status');
});

it('requires email and product columns before continuing', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv('transactions.csv', "Email,Product,Amount\nalice@example.com,Pro Plan,29.00\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->call('mapColumns')
        ->assertHasErrors(['emailColumnIndex', 'productColumnIndex']);

    expect(Transaction::where('organization_id', $organization->id)->count())->toBe(0);
});

it('requires an amount or a subtotal column before continuing', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv('transactions.csv', "Email,Product,Notes\nalice@example.com,Pro Plan,n/a\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->call('mapColumns')
        ->assertHasErrors(['amountColumnIndex']);

    expect(Transaction::where('organization_id', $organization->id)->count())->toBe(0);
});

it('shows the unique status values defaulted to Success, when a status column is mapped', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Amount,Status\nalice@example.com,Pro Plan,29.00,paid\nbob@example.com,Free Trial,0,trial\ncarol@example.com,Pro Plan,29.00,paid\n"
    );

    $test = Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('amountColumnIndex', '2')
        ->set('statusColumnIndex', '3')
        ->call('mapColumns')
        ->assertHasNoErrors()
        ->assertSet('mapped', true)
        ->assertSet('statusValues', ['paid', 'trial'])
        ->assertSet('statusValueAssignments', ['success', 'success']);

    $test->assertSee('paid')->assertSee('trial');

    expect(Transaction::where('organization_id', $organization->id)->count())->toBe(0);
});

it('imports rows without a status column as Success by default', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv('transactions.csv', "Email,Product,Amount\nalice@example.com,Pro Plan,29.00\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('amountColumnIndex', '2')
        ->call('mapColumns')
        ->assertHasNoErrors()
        ->assertSet('imported', true)
        ->assertSet('importedCount', 1);

    expect(Transaction::where('organization_id', $organization->id)->first()->status)->toBe(TransactionStatus::Success);
});

it('assigns success, fail or refund to transactions based on the mapped status values', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Amount,Status\nalice@example.com,Pro Plan,29.00,paid\nbob@example.com,Free Trial,0,declined\ncarol@example.com,Enterprise Plan,99.00,refunded\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('amountColumnIndex', '2')
        ->set('statusColumnIndex', '3')
        ->call('mapColumns')
        ->assertSet('statusValues', ['paid', 'declined', 'refunded'])
        ->set('statusValueAssignments', ['success', 'fail', 'refund'])
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importedCount', 3)
        ->assertSet('successCount', 1)
        ->assertSet('failCount', 1)
        ->assertSet('refundCount', 1);

    $transactions = Transaction::where('organization_id', $organization->id)->get();

    expect($transactions->firstWhere('product_name', 'Pro Plan')->status)->toBe(TransactionStatus::Success);
    expect($transactions->firstWhere('product_name', 'Free Trial')->status)->toBe(TransactionStatus::Fail);
    expect($transactions->firstWhere('product_name', 'Enterprise Plan')->status)->toBe(TransactionStatus::Refund);
});

it('creates a new person for an unknown email and reuses an existing person for a known email', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);
    $existingPerson = Person::factory()->create(['email_hash' => hash('sha256', 'bob@example.com')]);
    TenantPeople::factory()->create([
        'organization_id' => $organization->id,
        'person_id' => $existingPerson->id,
        'email' => 'bob@example.com',
    ]);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,First Name,Last Name,Product,Amount\nalice@example.com,Alice,Doe,Pro Plan,29.00\nbob@example.com,Bob,Smith,Pro Plan,29.00\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('firstNameColumnIndex', '1')
        ->set('lastNameColumnIndex', '2')
        ->set('productColumnIndex', '3')
        ->set('amountColumnIndex', '4')
        ->call('mapColumns')
        ->assertSet('importedCount', 2);

    expect(TenantPeople::where('organization_id', $organization->id)->count())->toBe(2);

    $newTenantPerson = TenantPeople::where('organization_id', $organization->id)->where('email', 'alice@example.com')->first();
    expect($newTenantPerson->full_name)->toBe('Alice Doe');

    $reusedTransaction = Transaction::where('organization_id', $organization->id)
        ->whereHas('person', fn ($query) => $query->where('id', $existingPerson->id))
        ->first();
    expect($reusedTransaction)->not->toBeNull();
});

it('splits a mapped full name column into first and last name', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Name,Product,Amount\nalice@example.com,Alice Doe,Pro Plan,29.00\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('fullNameColumnIndex', '1')
        ->set('productColumnIndex', '2')
        ->set('amountColumnIndex', '3')
        ->call('mapColumns')
        ->assertSet('importedCount', 1);

    $tenantPerson = TenantPeople::where('organization_id', $organization->id)->first();

    expect($tenantPerson->first_name)->toBe('Alice');
    expect($tenantPerson->last_name)->toBe('Doe');
});

it('maps accounting breakdown columns and computes the amount from them when amount is not mapped', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Subtotal,Tax,Discount,Fees\nalice@example.com,Pro Plan,29.00,2.32,0,1.00\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('subtotalColumnIndex', '2')
        ->set('taxColumnIndex', '3')
        ->set('discountColumnIndex', '4')
        ->set('feesColumnIndex', '5')
        ->call('mapColumns')
        ->assertSet('importedCount', 1);

    $transaction = Transaction::where('organization_id', $organization->id)->first();

    expect($transaction->subtotal_cents)->toBe(2900);
    expect($transaction->tax_cents)->toBe(232);
    expect($transaction->discount_cents)->toBe(0);
    expect($transaction->fees_cents)->toBe(100);
    expect($transaction->amount_cents)->toBe(3032);
});

it('computes amount from a mapped total column, storing a negative discount as a positive informational value', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Total,Fees,Discount\nalice@example.com,Pro Plan,21.35,1.18,-19.00\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('totalColumnIndex', '2')
        ->set('feesColumnIndex', '3')
        ->set('discountColumnIndex', '4')
        ->call('mapColumns')
        ->assertSet('importedCount', 1);

    $transaction = Transaction::where('organization_id', $organization->id)->first();

    expect($transaction->total_cents)->toBe(2135);
    expect($transaction->amount_cents)->toBe(2017);
    expect($transaction->discount_cents)->toBe(1900);
});

it('stores a negative subtotal as a positive amount, using status rather than sign to record a refund', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Subtotal,Status\nalice@example.com,All Access Pass,-299.00,refunded\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('subtotalColumnIndex', '2')
        ->set('statusColumnIndex', '3')
        ->call('mapColumns')
        ->assertSet('statusValues', ['refunded'])
        ->set('statusValueAssignments', ['refund'])
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importedCount', 1);

    $transaction = Transaction::where('organization_id', $organization->id)->first();

    expect($transaction->subtotal_cents)->toBe(29900);
    expect($transaction->amount_cents)->toBe(29900);
    expect($transaction->status)->toBe(TransactionStatus::Refund);
});

it('uses a mapped amount column instead of calculating it from the breakdown', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Amount,Subtotal,Fees\nalice@example.com,Pro Plan,30.00,29.00,1.00\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('amountColumnIndex', '2')
        ->set('subtotalColumnIndex', '3')
        ->set('feesColumnIndex', '4')
        ->call('mapColumns')
        ->assertSet('importedCount', 1);

    expect(Transaction::where('organization_id', $organization->id)->first()->amount_cents)->toBe(3000);
});

it('matches an existing product by name case-insensitively and leaves unmatched products uncatalogued', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);
    $product = Product::factory()->create(['organization_id' => $organization->id, 'name' => 'Pro Plan']);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Amount\nalice@example.com,pro plan,29.00\nbob@example.com,Mystery Bundle,9.00\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('amountColumnIndex', '2')
        ->call('mapColumns')
        ->assertSet('importedCount', 2);

    $transactions = Transaction::where('organization_id', $organization->id)->get();

    expect($transactions->firstWhere('product_name', 'pro plan')->product_id)->toBe($product->id);
    expect($transactions->firstWhere('product_name', 'Mystery Bundle')->product_id)->toBeNull();
});

it('skips rows with a missing or invalid email or an unparseable amount, and reports them', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $csv = fakeTransactionsCsv(
        'transactions.csv',
        "Email,Product,Amount\nalice@example.com,Pro Plan,29.00\nnot-an-email,Pro Plan,29.00\nbob@example.com,Pro Plan,not-a-number\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '0')
        ->set('productColumnIndex', '1')
        ->set('amountColumnIndex', '2')
        ->call('mapColumns')
        ->assertSet('importedCount', 1)
        ->assertSet('skippedCount', 2);

    expect(Transaction::where('organization_id', $organization->id)->count())->toBe(1);
});

it('does not duplicate transactions when re-importing rows with the same external id, updating them instead', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    $firstImport = fakeTransactionsCsv(
        'transactions.csv',
        "ID,Email,Product,Amount,Status\ntxn_1,alice@example.com,Pro Plan,29.00,paid\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $firstImport)
        ->set('emailColumnIndex', '1')
        ->set('externalIdColumnIndex', '0')
        ->set('productColumnIndex', '2')
        ->set('amountColumnIndex', '3')
        ->set('statusColumnIndex', '4')
        ->call('mapColumns')
        ->set('statusValueAssignments', ['success'])
        ->call('import')
        ->assertSet('importedCount', 1);

    expect(Transaction::where('organization_id', $organization->id)->count())->toBe(1);

    $secondImport = fakeTransactionsCsv(
        'transactions.csv',
        "ID,Email,Product,Amount,Status\ntxn_1,alice@example.com,Pro Plan,29.00,refunded\n"
    );

    Volt::actingAs($owner)
        ->test('pages.organizations.transactions.import', ['organization' => $organization])
        ->set('csvFile', $secondImport)
        ->set('emailColumnIndex', '1')
        ->set('externalIdColumnIndex', '0')
        ->set('productColumnIndex', '2')
        ->set('amountColumnIndex', '3')
        ->set('statusColumnIndex', '4')
        ->call('mapColumns')
        ->set('statusValueAssignments', ['refund'])
        ->call('import')
        ->assertSet('importedCount', 1);

    $transactions = Transaction::where('organization_id', $organization->id)->get();

    expect($transactions)->toHaveCount(1);
    expect($transactions->first()->external_id)->toBe('txn_1');
    expect($transactions->first()->status)->toBe(TransactionStatus::Refund);
});

it('always inserts fresh rows when no external id column is mapped, even for identical rows', function () {
    $organization = Organization::factory()->create();
    $owner = ownerOf($organization);

    foreach (range(1, 2) as $attempt) {
        $csv = fakeTransactionsCsv('transactions.csv', "Email,Product,Amount\nalice@example.com,Pro Plan,29.00\n");

        Volt::actingAs($owner)
            ->test('pages.organizations.transactions.import', ['organization' => $organization])
            ->set('csvFile', $csv)
            ->set('emailColumnIndex', '0')
            ->set('productColumnIndex', '1')
            ->set('amountColumnIndex', '2')
            ->call('mapColumns')
            ->assertSet('importedCount', 1);
    }

    expect(Transaction::where('organization_id', $organization->id)->count())->toBe(2);
});
