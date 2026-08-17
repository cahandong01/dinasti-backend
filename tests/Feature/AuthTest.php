<?php

use App\Models\User;

test('login pakai email berhasil', function () {
    $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

    $response = $this->postJson('/api/login', [
        'login' => $user->email,
        'password' => 'rahasia123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['user', 'token']);
});

test('login pakai username berhasil', function () {
    $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

    $response = $this->postJson('/api/login', [
        'login' => $user->username,
        'password' => 'rahasia123',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['user', 'token']);
});

test('login gagal karena password salah', function () {
    $user = User::factory()->create(['password' => bcrypt('rahasia123')]);

    $response = $this->postJson('/api/login', [
        'login' => $user->email,
        'password' => 'salah',
    ]);

    $response->assertStatus(401);
});

test('login gagal karena akun tidak ada', function () {
    $response = $this->postJson('/api/login', [
        'login' => 'tidakada@example.com',
        'password' => 'apapun',
    ]);

    $response->assertStatus(401);
});