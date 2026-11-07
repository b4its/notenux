<?php

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it renders the main gamified panel view with initial notes and dual sidebars', function () {
    $note = Note::factory()->create([
        'title' => 'Arcane Quest 01',
        'content' => '<h2>Quest Description</h2><p>Venture into the neon dungeon.</p>',
    ]);

    $response = $this->get(route('panel'));

    $response->assertOk()
        ->assertViewIs('panel')
        ->assertSee('NOTENUX')
        ->assertSee('primary-nav-sidebar')
        ->assertSee('notes-explorer-sidebar')
        ->assertSee('primary-sidebar-toggle-btn')
        ->assertSee('explorer-toggle-btn')
        ->assertSee('theme-toggle-btn')
        ->assertSee('sound-toggle-btn')
        ->assertSee('inspect-chunks-btn')
        ->assertSee('toast-container')
        ->assertSee('zoom-in-btn')
        ->assertSee('zoom-out-btn')
        ->assertSee('zoom-reset-btn')
        ->assertSee('insert-table-btn')
        ->assertSee('markdown-paste-btn')
        ->assertSee('editor-zoom-wrapper')
        ->assertSee('font-family-select')
        ->assertSee('font-size-select')
        ->assertSee('editor-content')
        ->assertSee('chunk-inspector-modal')
        ->assertSee('delete-confirm-modal')
        ->assertSee('delete-modal-scroll-title')
        ->assertSee('cancel-delete-modal-btn')
        ->assertSee('confirm-delete-modal-btn')
        ->assertSee('archive-note-btn')
        ->assertSee('font-awesome', false)
        ->assertSee('Arcane Quest 01');
});

test('it renders interactive visual delete confirmation modal instead of default browser alert', function () {
    $response = $this->get(route('panel'));

    $response->assertOk()
        ->assertSee('delete-confirm-modal')
        ->assertSee('BANISH SCROLL CONFIRMATION')
        ->assertSee('Banish Scroll to Digital Void?')
        ->assertSee('close-delete-modal-x')
        ->assertSee('cancel-delete-modal-btn')
        ->assertSee('confirm-delete-modal-btn')
        ->assertSee('YES, BANISH SCROLL');
});

test('it supports responsive mobile dual-sidebar drawers and theme attributes', function () {
    $response = $this->get(route('panel'));

    $response->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('mobile-nav-toggle-btn')
        ->assertSee('mobile-explorer-toggle-btn')
        ->assertSee('sidebar-overlay');
});

test('it renders symmetrical collapsed sidebar structure with centered avatars, level badges, and category tooltips', function () {
    $response = $this->get(route('panel'));

    $response->assertOk()
        ->assertSee('player-profile-section')
        ->assertSee('player-status-card')
        ->assertSee('player-avatar-box')
        ->assertSee('collapsed-level-badge')
        ->assertSee('primary-nav-links')
        ->assertSee('collapsed-cat-header')
        ->assertSee('title="All Scrolls"', false);
});
