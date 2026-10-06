<?php

use App\Models\User;

test('unauthenticated users are redirected to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('authenticated users can access the dashboard', function () {
    $user = User::first();
    if (! $user) {
        $user = User::factory()->create();
    }

    $response = $this->actingAs($user)->get('/');

    $response->assertStatus(200);
});
