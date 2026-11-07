<?php

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access studio and is redirected to login', function () {
    $response = $this->get('/studio');

    $response->assertRedirect('/login');
});

test('authenticated scribe can access studio and view retro workspace', function () {
    $user = User::factory()->create([
        'name' => 'Archie Master Scribe',
    ]);

    Note::factory()->create([
        'title' => 'Ancient Shard Manuscript',
    ]);

    $response = $this->actingAs($user)->get(route('studio'));

    $response->assertOk()
        ->assertViewIs('panel')
        ->assertSee('NOTENUX')
        ->assertSee('STUDIO v3.0')
        ->assertSee('Ancient Shard Manuscript')
        ->assertSee('primary-nav-sidebar')
        ->assertSee('notes-explorer-sidebar')
        ->assertSee('logout-btn');
});

test('legacy panel route redirects authenticated or guest user to studio', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('panel'));

    $response->assertRedirect(route('studio'));
});
