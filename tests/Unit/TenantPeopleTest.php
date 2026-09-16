<?php

use App\Models\TenantPeople;

it('splits a full name into first and last name on the first space', function () {
    $tenantPerson = new TenantPeople;
    $tenantPerson->full_name = 'Ada Lovelace';

    expect($tenantPerson->first_name)->toBe('Ada');
    expect($tenantPerson->last_name)->toBe('Lovelace');
});

it('splits a full name with more than two words on the first space only', function () {
    $tenantPerson = new TenantPeople;
    $tenantPerson->full_name = 'Mary Jane Watson';

    expect($tenantPerson->first_name)->toBe('Mary');
    expect($tenantPerson->last_name)->toBe('Jane Watson');
});

it('treats a single-word name as the first name with no last name', function () {
    $tenantPerson = new TenantPeople;
    $tenantPerson->full_name = 'Cher';

    expect($tenantPerson->first_name)->toBe('Cher');
    expect($tenantPerson->last_name)->toBeNull();
});

it('clears first and last name when set to a blank value', function () {
    $tenantPerson = new TenantPeople;
    $tenantPerson->first_name = 'Ada';
    $tenantPerson->last_name = 'Lovelace';
    $tenantPerson->full_name = '   ';

    expect($tenantPerson->first_name)->toBeNull();
    expect($tenantPerson->last_name)->toBeNull();
});

it('combines first and last name back into full_name when read', function () {
    $tenantPerson = new TenantPeople;
    $tenantPerson->first_name = 'Ada';
    $tenantPerson->last_name = 'Lovelace';

    expect($tenantPerson->full_name)->toBe('Ada Lovelace');
});

it('returns null for full_name when no name has been captured', function () {
    $tenantPerson = new TenantPeople;

    expect($tenantPerson->full_name)->toBeNull();
});
