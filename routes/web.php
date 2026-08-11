<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'org.context'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth', 'org.context'])
    ->name('profile');

require __DIR__.'/auth.php';
require __DIR__.'/organizations.php';
