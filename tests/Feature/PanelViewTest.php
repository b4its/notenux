<?php

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it renders the main gamified panel view with initial notes', function () {
    $note = Note::factory()->create([
        'title' => 'Arcane Quest 01',
        'content' => '<h2>Quest Description</h2><p>Venture into the neon dungeon.</p>',
    ]);

    $response = $this->get(route('panel'));

    $response->assertOk()
        ->assertViewIs('panel')
        ->assertSee('NOTENUX')
        ->assertSee('notenux-sidebar')
        ->assertSee('theme-toggle-btn')
        ->assertSee('sound-toggle-btn')
        ->assertSee('inspect-chunks-btn')
        ->assertSee('font-family-select')
        ->assertSee('font-size-select')
        ->assertSee('editor-content')
        ->assertSee('chunk-inspector-modal')
        ->assertSee('Arcane Quest 01');
});

test('it supports responsive mobile drawer and theme attributes', function () {
    $response = $this->get(route('panel'));

    $response->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('mobile-toggle-btn')
        ->assertSee('sidebar-overlay');
});
