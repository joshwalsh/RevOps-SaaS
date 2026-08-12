<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('auth')->group(function () {
    Volt::route('organizations/create', 'pages.organizations.create')
        ->name('organizations.create');

    Volt::route('organizations/{organization}/members', 'pages.organizations.members')
        ->middleware(['verified', 'org.context'])
        ->name('organizations.members');
});

Volt::route('organizations/invitations/{invitation}/accept', 'pages.organizations.accept-invitation')
    ->middleware(['signed'])
    ->name('organizations.invitations.accept');
