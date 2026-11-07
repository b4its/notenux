<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('landing page renders successfully for guests with interactive sections', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertViewIs('landing')
        ->assertSee('NOTENUX')
        ->assertSee('Tingkatkan Level Scribe')
        ->assertSee('BUKA STUDIO')
        ->assertSee('hero-studio-cta')
        ->assertSee('hero-register-cta')
        ->assertSee('hero-login-cta')
        ->assertSee('theme-toggle-btn')
        ->assertSee('demo-editor-input')
        ->assertSee('demo-xp-bar')
        ->assertSee('CHUNK MATRIX ENGINE')
        ->assertSee('DISTRACTION-FREE ZEN MODE')
        ->assertSee('font-awesome', false);
});

test('landing page includes dark and light theme toggle support', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('data-theme="dark"', false)
        ->assertSee('theme-toggle-btn')
        ->assertSee('TEMA');
});

test('landing page renders authenticated user session controls', function () {
    $user = User::factory()->create([
        'name' => 'Commander Cyber',
    ]);

    $response = $this->actingAs($user)->get(route('landing'));

    $response->assertOk()
        ->assertSee('Commander Cyber')
        ->assertSee('nav-studio-btn')
        ->assertSee('nav-logout-btn')
        ->assertDontSee('hero-register-cta');
});

test('main cta links directly to studio route', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee(route('studio'), false);
});

test('landing page and studio render horizontal, vertical, and icon logos with dark/light orientation', function () {
    $response = $this->get(route('landing'));

    $response->assertOk()
        ->assertSee('assets/logo/dark/icon_horizontal_dark.png')
        ->assertSee('assets/logo/light/icon_horizontal_light.png')
        ->assertSee('assets/logo/dark/icon_vertical_dark.png')
        ->assertSee('assets/logo/light/icon_vertical_light.png')
        ->assertSee('assets/logo/dark/icon_dark.png');
});
