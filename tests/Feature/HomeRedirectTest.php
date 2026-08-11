<?php

use App\Models\User;

it('redirects guests from the home page to login', function () {
    $this->get('/')
        ->assertRedirect(route('login'));
});

it('redirects authenticated users from the home page to the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('dashboard'));
});
