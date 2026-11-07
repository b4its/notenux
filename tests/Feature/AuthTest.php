<?php

use App\Models\Category;
use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guest can view the login interface', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertViewIs('auth.login')
        ->assertSee('MASUK SCRIBE')
        ->assertSee('login-submit-btn')
        ->assertSee('to-register-btn')
        ->assertSee('theme-toggle-btn');
});

test('guest can view the registration interface', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertViewIs('auth.register')
        ->assertSee('REGISTER SCRIBE')
        ->assertSee('register-submit-btn')
        ->assertSee('to-login-btn')
        ->assertSee('theme-toggle-btn');
});

test('new user can register with valid data and be authenticated', function () {
    $payload = [
        'name' => 'Archie Chronos',
        'email' => 'archie@notenux.dev',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
    ];

    $response = $this->post(route('register'), $payload);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'name' => 'Archie Chronos',
        'email' => 'archie@notenux.dev',
    ]);

    expect(Note::count())->toBe(1);
    expect(Note::first()->title)->toBe('Halo! Selamat Datang di Notenux');
    expect(Note::first()->category)->toBe('catatan');

    $registeredUser = User::where('email', 'archie@notenux.dev')->first();
    expect(Category::where('user_id', $registeredUser->id)->where('slug', 'catatan')->exists())->toBeTrue();
    expect(Category::where('user_id', $registeredUser->id)->where('slug', 'tugas')->exists())->toBeTrue();

    $response->assertRedirect(route('studio'));
});

test('registration rejects password confirmation mismatch', function () {
    $payload = [
        'name' => 'Archie Chronos',
        'email' => 'archie@notenux.dev',
        'password' => 'secret12345',
        'password_confirmation' => 'different-password',
    ];

    $response = $this->from(route('register'))->post(route('register'), $payload);

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors('password');
});

test('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'existing@notenux.dev']);

    $payload = [
        'name' => 'Another Scribe',
        'email' => 'existing@notenux.dev',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
    ];

    $response = $this->from(route('register'))->post(route('register'), $payload);

    $this->assertGuest();
    $response->assertRedirect(route('register'));
    $response->assertSessionHasErrors('email');
});

test('user can authenticate using valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'scribe@notenux.dev',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->post(route('login'), [
        'email' => 'scribe@notenux.dev',
        'password' => 'secret-password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('studio'));
});

test('user cannot authenticate with invalid credentials', function () {
    User::factory()->create([
        'email' => 'scribe@notenux.dev',
        'password' => Hash::make('secret-password'),
    ]);

    $response = $this->from(route('login'))->post(route('login'), [
        'email' => 'scribe@notenux.dev',
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
});

test('authenticated user visiting login or register is redirected to studio', function () {
    $user = User::factory()->create();

    $responseLogin = $this->actingAs($user)->get(route('login'));
    $responseLogin->assertRedirect(route('studio'));

    $responseRegister = $this->actingAs($user)->get(route('register'));
    $responseRegister->assertRedirect(route('studio'));
});

test('authenticated user can logout and session is cleared', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('landing'));
});
