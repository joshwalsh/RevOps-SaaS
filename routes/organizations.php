<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('auth')->group(function () {
    Volt::route('organizations/create', 'pages.organizations.create')
        ->name('organizations.create');

    Volt::route('organizations/{organization}/users', 'pages.organizations.users')
        ->middleware(['verified', 'org.context'])
        ->name('organizations.users');

    Volt::route('organizations/{organization}/products', 'pages.organizations.products')
        ->middleware(['verified', 'org.context'])
        ->name('organizations.products');

    Volt::route('organizations/{organization}/transactions', 'pages.organizations.transactions')
        ->middleware(['verified', 'org.context'])
        ->name('organizations.transactions');

    Volt::route('organizations/{organization}/events', 'pages.organizations.events')
        ->middleware(['verified', 'org.context'])
        ->name('organizations.events');

    Volt::route('organizations/{organization}/people', 'pages.organizations.people.index')
        ->middleware(['verified', 'org.context'])
        ->name('organizations.people.index');

    Volt::route('organizations/{organization}/people/{tenantPersonId}', 'pages.organizations.people.show')
        ->middleware(['verified', 'org.context'])
        ->name('organizations.people.show');
});

Volt::route('organizations/invitations/{invitation}/accept', 'pages.organizations.accept-invitation')
    ->middleware(['signed'])
    ->name('organizations.invitations.accept');
