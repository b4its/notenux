<?php

use App\Models\Category;
use App\Models\Note;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it renders the personalized categories guild page', function () {
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();

    $customCat = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Alchemy Formulas',
        'slug' => 'alchemy-formulas',
        'icon' => 'fa-solid fa-flask',
        'color' => 'emerald',
    ]);

    $response = $this->actingAs($user)->get(route('categories.index'));

    $response->assertOk()
        ->assertSee('CATEGORY GUILD')
        ->assertSee('FORGE CATEGORY')
        ->assertSee('Alchemy Formulas')
        ->assertSee('Quests')
        ->assertSee('Spellbooks');
});

test('it returns categories list via api including note counts', function () {
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();

    $customCat = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Dragon Lore',
        'slug' => 'dragon-lore',
    ]);

    Note::factory()->create([
        'category' => 'dragon-lore',
    ]);

    $response = $this->actingAs($user)->getJson(route('api.categories.index'));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonFragment([
            'slug' => 'dragon-lore',
            'name' => 'Dragon Lore',
            'notes_count' => 1,
        ]);
});

test('a user can forge a new category via web form', function () {
    $user = User::factory()->create();

    $payload = [
        'name' => 'Boss Raids Tactics',
        'slug' => 'boss-tactics',
        'icon' => 'fa-solid fa-skull',
        'color' => 'rose',
        'description' => 'Tactics for defeating raid bosses in the outer realm.',
    ];

    $response = $this->actingAs($user)->post(route('categories.store'), $payload);

    $response->assertRedirect(route('categories.index'))
        ->assertSessionHas('status', 'success');

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Boss Raids Tactics',
        'slug' => 'boss-tactics',
        'icon' => 'fa-solid fa-skull',
        'color' => 'rose',
        'is_system' => false,
    ]);
});

test('a user can create a personalized category via json api', function () {
    $user = User::factory()->create();

    $payload = [
        'name' => 'Dungeon Secrets',
        'slug' => 'dungeon-secrets',
        'icon' => 'fa-solid fa-key',
        'color' => 'amber',
        'description' => 'Hidden passage coordinates and puzzle solutions.',
    ];

    $response = $this->actingAs($user)->postJson(route('api.categories.store'), $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Dungeon Secrets')
        ->assertJsonPath('data.slug', 'dungeon-secrets')
        ->assertJsonPath('data.color', 'amber');

    $this->assertDatabaseHas('categories', [
        'user_id' => $user->id,
        'name' => 'Dungeon Secrets',
        'slug' => 'dungeon-secrets',
    ]);
});

test('it auto-generates slug when empty and resolves slug collisions', function () {
    $user = User::factory()->create();

    // Create first category without explicit slug
    $res1 = $this->actingAs($user)->postJson(route('api.categories.store'), [
        'name' => 'Dark Magic Arts',
        'color' => 'purple',
    ]);

    $res1->assertCreated()
        ->assertJsonPath('data.slug', 'dark-magic-arts');

    // Create second category with conflicting slug
    $res2 = $this->actingAs($user)->postJson(route('api.categories.store'), [
        'name' => 'Dark Magic Arts',
        'slug' => 'dark-magic-arts',
        'color' => 'purple',
    ]);

    $res2->assertCreated();
    $secondSlug = $res2->json('data.slug');
    expect($secondSlug)->toStartWith('dark-magic-arts-');
});

test('a user can update their personalized category', function () {
    $user = User::factory()->create();
    $cat = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Old Relics',
        'color' => 'cyan',
    ]);

    $updatePayload = [
        'name' => 'Ancient Artifacts',
        'icon' => 'fa-solid fa-gem',
        'color' => 'amber',
        'description' => 'Rare archaeological findings.',
    ];

    $response = $this->actingAs($user)->putJson(route('api.categories.update', $cat), $updatePayload);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Ancient Artifacts')
        ->assertJsonPath('data.color', 'amber');

    $this->assertDatabaseHas('categories', [
        'id' => $cat->id,
        'name' => 'Ancient Artifacts',
        'color' => 'amber',
    ]);
});

test('system and default categories can be modified and dismantled with automatic manuscript deletion', function () {
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();
    $systemCat = Category::where('slug', 'quest')->firstOrFail();

    $note = Note::factory()->create([
        'title' => 'Ancient Quest Scroll',
        'category' => 'quest',
    ]);

    // Update system category
    $resUpdate = $this->actingAs($user)->putJson(route('api.categories.update', $systemCat), [
        'name' => 'Legendary Quests',
        'slug' => 'legendary-quests',
        'color' => 'emerald',
    ]);
    $resUpdate->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Legendary Quests')
        ->assertJsonPath('data.slug', 'legendary-quests');

    $this->assertDatabaseHas('categories', [
        'id' => $systemCat->id,
        'name' => 'Legendary Quests',
        'slug' => 'legendary-quests',
    ]);

    // Check that existing notes had their category slug updated
    $this->assertDatabaseHas('notes', [
        'title' => 'Ancient Quest Scroll',
        'category' => 'legendary-quests',
    ]);

    // Delete category
    $resDelete = $this->actingAs($user)->deleteJson(route('api.categories.destroy', $systemCat));
    $resDelete->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseMissing('categories', [
        'id' => $systemCat->id,
    ]);

    // Notes associated with deleted category are automatically deleted
    $this->assertDatabaseMissing('notes', [
        'id' => $note->id,
    ]);
});

test('deleting the general category automatically deletes all scrolls in that category', function () {
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();
    $generalCat = Category::where('slug', 'general')->firstOrFail();

    $note = Note::factory()->create([
        'title' => 'Miscellaneous Journal',
        'category' => 'general',
    ]);

    $resDelete = $this->actingAs($user)->deleteJson(route('api.categories.destroy', $generalCat));
    $resDelete->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseMissing('categories', [
        'id' => $generalCat->id,
    ]);

    $this->assertDatabaseMissing('notes', [
        'id' => $note->id,
    ]);
});

test('studio panel view renders quick edit, delete, and forge controls for category selector', function () {
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('studio'));

    $response->assertOk()
        ->assertSee('quick-edit-category-btn')
        ->assertSee('quick-delete-category-btn')
        ->assertSee('quick-forge-category-btn')
        ->assertSee('quick-edit-category-modal')
        ->assertSee('quick-delete-category-modal')
        ->assertSee('quick-forge-category-modal');
});

test('category guild page renders edit and delete controls for all categories without core lock', function () {
    $this->seed(CategorySeeder::class);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('categories.index'));

    $response->assertOk()
        ->assertDontSee('Core category')
        ->assertSee('openEditCategoryModal')
        ->assertSee('openDeleteCategoryModal');
});

test('a user cannot update or delete another users category', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $cat1 = Category::factory()->create([
        'user_id' => $user1->id,
        'name' => 'User One Secret',
    ]);

    $resUpdate = $this->actingAs($user2)->putJson(route('api.categories.update', $cat1), [
        'name' => 'Hijacked',
    ]);
    $resUpdate->assertStatus(403);

    $resDelete = $this->actingAs($user2)->deleteJson(route('api.categories.destroy', $cat1));
    $resDelete->assertStatus(403);

    $this->assertDatabaseHas('categories', [
        'id' => $cat1->id,
        'name' => 'User One Secret',
    ]);
});

test('deleting a personalized category automatically deletes its associated notes', function () {
    $user = User::factory()->create();
    $cat = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Temporary Tactical',
        'slug' => 'temp-tactical',
    ]);

    $note = Note::factory()->create([
        'title' => 'Assault Plan Beta',
        'category' => 'temp-tactical',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('api.categories.destroy', $cat));

    $response->assertOk()
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseMissing('categories', [
        'id' => $cat->id,
    ]);

    $this->assertDatabaseMissing('notes', [
        'id' => $note->id,
    ]);
});

test('note creation with personalized category is permitted and filterable', function () {
    $user = User::factory()->create();
    $cat = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Blacksmithing',
        'slug' => 'blacksmithing',
    ]);

    $payload = [
        'title' => 'Forging Mithril Armor',
        'category' => 'blacksmithing',
        'content' => '<p>Heat the forge to 2000 degrees.</p>',
    ];

    $resCreate = $this->actingAs($user)->postJson(route('notes.store'), $payload);
    $resCreate->assertCreated()
        ->assertJsonPath('data.category', 'blacksmithing');

    // Query filter by category
    $resFilter = $this->actingAs($user)->getJson(route('notes.index', ['category' => 'blacksmithing']));
    $resFilter->assertOk()
        ->assertJsonPath('status', 'success');

    $notes = $resFilter->json('data');
    expect(count($notes))->toBe(1);
    expect($notes[0]['category'])->toBe('blacksmithing');
});

test('category creation validates required inputs', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.categories.store'), [
        'name' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});
