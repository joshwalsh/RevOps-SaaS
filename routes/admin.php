<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('organizations', 'pages.admin.organizations')->name('organizations');

    Volt::route('members', 'pages.admin.members')->name('members');
});
