<?php

use App\Models\Category;
use App\Models\Note;
use App\Models\NoteChunk;
use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('black-box: category slugs cannot collide across owners in the shared manuscript namespace', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $existing = Category::factory()->create(['user_id' => $other->id, 'slug' => 'research']);
    $note = Note::factory()->create(['category' => 'research']);

    $response = $this->actingAs($owner)->postJson(route('api.categories.store'), ['name' => 'Research', 'slug' => 'research']);

    $response->assertCreated();
    expect($response->json('data.slug'))->not->toBe($existing->slug);
    $this->assertDatabaseHas('notes', ['id' => $note->id, 'category' => 'research']);
});

test('black-box: normalized slugs remain usable and fit the manuscript category limit', function (string $name, ?string $slug) {
    $user = User::factory()->create();
    Category::factory()->create(['user_id' => $user->id, 'slug' => str_repeat('x', 64)]);

    $response = $this->actingAs($user)->postJson(route('api.categories.store'), ['name' => $name, 'slug' => $slug]);

    $response->assertCreated();
    expect(mb_strlen($response->json('data.slug')))->toBeLessThanOrEqual(64);
    expect($response->json('data.slug'))->not->toBe('all')->not->toBeEmpty();
})->with([
    'maximum length collision' => ['Maximum', str_repeat('x', 64)],
    'reserved filter' => ['All', 'all'],
    'normalized empty' => ['Symbols', '___'],
    'expanded transliteration' => [str_repeat('ß', 64), null],
]);

test('black-box: category presentation rejects unsafe or unsupported values', function (array $input, string $field) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('api.categories.store'), ['name' => 'Safe'] + $input)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseCount('categories', 0);
})->with([
    'icon injection' => [['icon' => 'fa-solid" onclick="alert(1)'], 'icon'],
    'unknown color' => [['color' => 'not-a-color'], 'color'],
]);

test('white-box: renaming a category rolls back its manuscripts when category storage fails', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id, 'slug' => 'original']);
    $note = Note::factory()->create(['category' => 'original']);
    Event::listen('eloquent.updating: App\\Models\\Category', function () {
        throw new RuntimeException('Simulated category storage failure');
    });

    $this->actingAs($user)->putJson(route('api.categories.update', $category), ['name' => 'Renamed', 'slug' => 'renamed'])->assertServerError();

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'category' => 'original']);
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'original']);
});

test('white-box: deleting a category rolls back manuscript deletion when category deletion fails', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id, 'slug' => 'original']);
    Category::factory()->create(['user_id' => $user->id, 'slug' => 'general']);
    $note = Note::factory()->create(['category' => 'original']);
    Event::listen('eloquent.deleting: App\\Models\\Category', function () {
        throw new RuntimeException('Simulated category storage failure');
    });

    $this->actingAs($user)->deleteJson(route('api.categories.destroy', $category))->assertServerError();

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'category' => 'original']);
    $this->assertModelExists($category);
});

test('white-box: deleting a category rolls back category deletion when manuscript deletion fails', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id, 'slug' => 'original']);
    $note = Note::factory()->create(['category' => 'original']);
    Event::listen('eloquent.deleting: App\\Models\\Note', function () {
        throw new RuntimeException('Simulated manuscript deletion failure');
    });

    $this->actingAs($user)->deleteJson(route('api.categories.destroy', $category))->assertServerError();

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'category' => 'original']);
    $this->assertModelExists($category);
});

test('black-box: category deletion deletes associated manuscripts atomically without affecting other categories', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $owner->id, 'slug' => 'temporary']);
    $note = Note::factory()->create(['category' => 'temporary']);
    $otherNote = Note::factory()->create(['category' => 'other-category']);

    $response = $this->actingAs($owner)->deleteJson(route('api.categories.destroy', $category));

    $response->assertOk();
    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    $this->assertDatabaseHas('notes', ['id' => $otherNote->id]);
});

test('black-box: deleting a category automatically removes all its manuscripts', function (string $slug) {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id, 'slug' => $slug]);
    $note = Note::factory()->create(['category' => $slug]);

    $this->actingAs($user)->deleteJson(route('api.categories.destroy', $category))->assertOk();

    $this->assertModelMissing($category);
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
})->with(['temporary', 'general']);

test('black-box: clearing a category description does not retain its previous value', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $user->id, 'description' => 'Old description']);

    $this->actingAs($user)->putJson(route('api.categories.update', $category), ['name' => 'Updated', 'description' => null])
        ->assertOk()->assertJsonPath('data.description', null);

    $this->assertDatabaseHas('categories', ['id' => $category->id, 'description' => null]);
});

test('black-box: category lock contention returns a retryable conflict without writing data', function () {
    $user = User::factory()->create();
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->with(5, Mockery::type(Closure::class))->andThrow(new LockTimeoutException);
    Cache::shouldReceive('lock')->once()->with('notenux:category-mutations', 30)->andReturn($lock);

    $this->actingAs($user)->postJson(route('api.categories.store'), ['name' => 'Busy'])
        ->assertStatus(409)->assertJsonPath('message', 'Categories are being updated. Please retry.');

    $this->assertDatabaseCount('categories', 0);
});

test('black-box: deleting a category cascades atomically to all associated notes and note chunks', function () {
    $user = User::factory()->create();

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Mythology',
        'slug' => 'mythology',
    ]);

    $otherCategory = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Chronicles',
        'slug' => 'chronicles',
    ]);

    $note1 = Note::factory()->create(['title' => 'Odin and Yggdrasil', 'category' => 'mythology']);
    $chunk1 = NoteChunk::factory()->create(['note_id' => $note1->id, 'chunk_uid' => 'chunk_myth_1']);

    $note2 = Note::factory()->create(['title' => 'Thor and Jormungandr', 'category' => 'mythology']);
    $chunk2 = NoteChunk::factory()->create(['note_id' => $note2->id, 'chunk_uid' => 'chunk_myth_2']);

    $otherNote = Note::factory()->create(['title' => 'Battle of Hastings', 'category' => 'chronicles']);
    $otherChunk = NoteChunk::factory()->create(['note_id' => $otherNote->id, 'chunk_uid' => 'chunk_chron_1']);

    $response = $this->actingAs($user)->deleteJson(route('api.categories.destroy', $category));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.deleted_id', $category->id)
        ->assertJsonPath('data.deleted_slug', 'mythology')
        ->assertJsonPath('data.deleted_notes_count', 2);

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note1->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note2->id]);
    $this->assertDatabaseMissing('note_chunks', ['id' => $chunk1->id]);
    $this->assertDatabaseMissing('note_chunks', ['id' => $chunk2->id]);

    $this->assertDatabaseHas('categories', ['id' => $otherCategory->id]);
    $this->assertDatabaseHas('notes', ['id' => $otherNote->id]);
    $this->assertDatabaseHas('note_chunks', ['id' => $otherChunk->id]);
});

test('white-box: direct eloquent model delete cascades to notes and chunks atomically', function () {
    $user = User::factory()->create();

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Arcane Secrets',
        'slug' => 'arcane-secrets',
    ]);

    $note = Note::factory()->create([
        'title' => 'Ancient Incantation',
        'category' => 'arcane-secrets',
    ]);
    $chunk = NoteChunk::factory()->create([
        'note_id' => $note->id,
    ]);

    $category->delete();

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    $this->assertDatabaseMissing('note_chunks', ['id' => $chunk->id]);
});

test('black-box: deleting a category with zero notes succeeds cleanly', function () {
    $user = User::factory()->create();

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'name' => 'Empty Chamber',
        'slug' => 'empty-chamber',
    ]);

    $response = $this->actingAs($user)->deleteJson(route('api.categories.destroy', $category));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.deleted_notes_count', 0);

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});
