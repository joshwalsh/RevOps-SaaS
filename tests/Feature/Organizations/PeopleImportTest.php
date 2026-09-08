<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Person;
use App\Models\TenantPeople;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('local');
});

function fakeCsv(string $name, string $content): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

it('lets an owner view the import page', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    Volt::actingAs($owner)
        ->test('pages.organizations.people.import', ['organization' => $organization])
        ->assertOk();
});

it('forbids a plain member from viewing the import page', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create();
    $organization->users()->attach($member, ['role' => OrganizationRole::User]);

    Volt::actingAs($member)
        ->test('pages.organizations.people.import', ['organization' => $organization])
        ->assertForbidden();
});

it('shows the detected columns after uploading a csv', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $csv = fakeCsv('people.csv', "First Name,Last Name,Email\nAda,Lovelace,ada@example.com\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.people.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->assertSee('First Name')
        ->assertSee('Last Name')
        ->assertSee('Email');
});

it('requires an email column to be mapped before importing', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $csv = fakeCsv('people.csv', "First Name,Last Name,Email\nAda,Lovelace,ada@example.com\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.people.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->call('import')
        ->assertHasErrors('emailColumnIndex');

    expect(TenantPeople::where('organization_id', $organization->id)->count())->toBe(0);
});

it('imports mapped rows into the organization', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $csv = fakeCsv('people.csv', "First Name,Last Name,Email\nAda,Lovelace,ada@example.com\nGrace,Hopper,grace@example.com\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.people.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '2')
        ->set('firstNameColumnIndex', '0')
        ->set('lastNameColumnIndex', '1')
        ->call('import')
        ->assertHasNoErrors()
        ->assertSet('importedCount', 2)
        ->assertSet('skippedCount', 0);

    $people = TenantPeople::where('organization_id', $organization->id)->get();

    expect($people)->toHaveCount(2);
    expect($people->pluck('email')->sort()->values()->all())->toBe(['ada@example.com', 'grace@example.com']);
    expect($people->firstWhere('email', 'ada@example.com')->fullName())->toBe('Ada Lovelace');
});

it('skips rows with a missing or invalid email and reports them', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);

    $csv = fakeCsv('people.csv', "First Name,Last Name,Email\nAda,Lovelace,ada@example.com\nNo,Email,\nBad,Format,not-an-email\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.people.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '2')
        ->set('firstNameColumnIndex', '0')
        ->set('lastNameColumnIndex', '1')
        ->call('import')
        ->assertSet('importedCount', 1)
        ->assertSet('skippedCount', 2);

    expect(TenantPeople::where('organization_id', $organization->id)->count())->toBe(1);
});

it('does not duplicate an existing tenant person when re-importing the same email', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();
    $organization->users()->attach($owner, ['role' => OrganizationRole::Owner]);
    $person = Person::factory()->create(['email_hash' => hash('sha256', 'ada@example.com')]);
    $existing = TenantPeople::factory()->create([
        'organization_id' => $organization->id,
        'person_id' => $person->id,
        'email' => 'ada@example.com',
        'first_name' => null,
    ]);

    $csv = fakeCsv('people.csv', "First Name,Email\nAda,ada@example.com\n");

    Volt::actingAs($owner)
        ->test('pages.organizations.people.import', ['organization' => $organization])
        ->set('csvFile', $csv)
        ->set('emailColumnIndex', '1')
        ->set('firstNameColumnIndex', '0')
        ->call('import')
        ->assertSet('importedCount', 1);

    expect(TenantPeople::where('organization_id', $organization->id)->count())->toBe(1);
    expect($existing->fresh()->first_name)->toBe('Ada');
});
