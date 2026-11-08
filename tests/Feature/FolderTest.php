<?php

use App\Models\Folder;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can list their folders with notes and children count', function () {
    $user = User::factory()->create();
    $parent = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Root Vault']);
    $child = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Sub Lore']);
    Note::factory()->create(['user_id' => $user->id, 'folder_id' => $parent->id]);

    $otherUser = User::factory()->create();
    Folder::factory()->create(['user_id' => $otherUser->id, 'name' => 'Enemy Folder']);

    $response = $this->actingAs($user)->getJson(route('api.folders.index'));

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data');

    $folderNames = collect($response->json('data'))->pluck('name')->all();
    expect($folderNames)->toContain('Root Vault', 'Sub Lore')
        ->not->toContain('Enemy Folder');
});

test('user can create a root folder via api', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Grimoire Archives',
        'color' => 'amber',
        'icon' => 'fa-solid fa-folder',
    ]);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Grimoire Archives')
        ->assertJsonPath('data.parent_id', null);

    $this->assertDatabaseHas('folders', [
        'user_id' => $user->id,
        'name' => 'Grimoire Archives',
        'parent_id' => null,
    ]);
});

test('user can create recursively nested folders', function () {
    $user = User::factory()->create();

    // Level 0: Root
    $root = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Level 0 Root']);

    // Level 1: Subfolder
    $sub1 = $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Level 1 Sub',
        'parent_id' => $root->id,
    ])->assertCreated()->json('data');

    // Level 2: Subfolder
    $sub2 = $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Level 2 Sub',
        'parent_id' => $sub1['id'],
    ])->assertCreated()->json('data');

    // Level 3: Subfolder
    $sub3 = $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Level 3 Sub',
        'parent_id' => $sub2['id'],
    ])->assertCreated()->json('data');

    expect($sub3['parent_id'])->toBe($sub2['id']);
    expect($sub2['parent_id'])->toBe($sub1['id']);
    expect($sub1['parent_id'])->toBe($root->id);

    $descendantIds = $root->fresh()->allDescendantIds();
    expect($descendantIds)->toContain($sub1['id'], $sub2['id'], $sub3['id']);
});

test('user cannot nest a folder into itself or its descendants (cycle prevention)', function () {
    $user = User::factory()->create();
    $root = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Root']);
    $child = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $root->id, 'name' => 'Child']);
    $grandchild = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $child->id, 'name' => 'Grandchild']);

    // Cannot nest into itself
    $this->actingAs($user)->putJson(route('api.folders.update', $root), [
        'parent_id' => $root->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    // Cannot nest root into grandchild
    $this->actingAs($user)->putJson(route('api.folders.update', $root), [
        'parent_id' => $grandchild->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

test('user cannot create or move folder into another users folder', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $folderB = Folder::factory()->create(['user_id' => $userB->id, 'name' => 'User B Folder']);

    $this->actingAs($userA)->postJson(route('api.folders.store'), [
        'name' => 'Malicious Subfolder',
        'parent_id' => $folderB->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

test('user can update their folder attributes', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Original Name']);

    $response = $this->actingAs($user)->putJson(route('api.folders.update', $folder), [
        'name' => 'Renamed Lore Vault',
        'color' => 'cyan',
        'icon' => 'fa-solid fa-gem',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Renamed Lore Vault')
        ->assertJsonPath('data.color', 'cyan')
        ->assertJsonPath('data.icon', 'fa-solid fa-gem');

    $this->assertDatabaseHas('folders', [
        'id' => $folder->id,
        'name' => 'Renamed Lore Vault',
    ]);
});

test('deleting a folder cascades subfolders and sets note folder_id to null', function () {
    $user = User::factory()->create();
    $parent = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Parent Folder']);
    $child = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $parent->id, 'name' => 'Child Folder']);

    $noteInParent = Note::factory()->create(['user_id' => $user->id, 'folder_id' => $parent->id]);
    $noteInChild = Note::factory()->create(['user_id' => $user->id, 'folder_id' => $child->id]);

    $response = $this->actingAs($user)->deleteJson(route('api.folders.destroy', $parent));

    $response->assertOk();

    // Both parent and child folder must be removed
    $this->assertDatabaseMissing('folders', ['id' => $parent->id]);
    $this->assertDatabaseMissing('folders', ['id' => $child->id]);

    // Notes must still exist and their folder_id must be null (root notes)
    expect($noteInParent->fresh()->folder_id)->toBeNull();
    expect($noteInChild->fresh()->folder_id)->toBeNull();
});

test('note can be created with folder_id and moved between folders', function () {
    $user = User::factory()->create();
    $folderA = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Folder Alpha']);
    $folderB = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Folder Beta']);

    // Create note in folder A
    $createRes = $this->actingAs($user)->postJson(route('notes.store'), [
        'title' => 'Ancient Quest Scroll',
        'category' => 'catatan',
        'folder_id' => $folderA->id,
    ]);

    $createRes->assertCreated()
        ->assertJsonPath('data.folder_id', $folderA->id);

    $noteId = $createRes->json('data.id');

    // Move to folder B
    $this->actingAs($user)->putJson(route('notes.update', $noteId), [
        'folder_id' => $folderB->id,
    ])->assertOk()
        ->assertJsonPath('data.folder_id', $folderB->id);

    // Remove from folder (move to root)
    $this->actingAs($user)->putJson(route('notes.update', $noteId), [
        'folder_id' => null,
    ])->assertOk()
        ->assertJsonPath('data.folder_id', null);
});

test('bulk update moves notes to a folder or clears folder', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Vault Target']);

    $note1 = Note::factory()->create(['user_id' => $user->id, 'folder_id' => null]);
    $note2 = Note::factory()->create(['user_id' => $user->id, 'folder_id' => null]);

    // Move both notes to $folder
    $this->actingAs($user)->postJson(route('notes.bulk-update'), [
        'ids' => [$note1->id, $note2->id],
        'folder_id' => $folder->id,
    ])->assertOk();

    expect($note1->fresh()->folder_id)->toBe($folder->id);
    expect($note2->fresh()->folder_id)->toBe($folder->id);

    // Bulk clear folder
    $this->actingAs($user)->postJson(route('notes.bulk-update'), [
        'ids' => [$note1->id, $note2->id],
        'clear_folder' => true,
    ])->assertOk();

    expect($note1->fresh()->folder_id)->toBeNull();
    expect($note2->fresh()->folder_id)->toBeNull();
});

test('NoteContentSanitizer preserves data-color-token and retro-color classes', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $input = '<p><span class="retro-color-black" data-color-token="black" style="color: rgb(0, 0, 0);">Teks Hitam Adaptif</span></p>';
    $clean = $sanitizer->sanitize($input);

    expect($clean)->toContain('data-color-token="black"')
        ->toContain('class="retro-color-black"')
        ->toContain('color: rgb(0, 0, 0)');
});

test('user can create a folder with a specific category or without category', function () {
    $user = User::factory()->create();

    // Folder with category
    $res1 = $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Work Vault',
        'category' => 'catatan',
    ]);
    $res1->assertCreated()
        ->assertJsonPath('data.category', 'catatan');

    // Folder without category
    $res2 = $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'General Vault',
        'category' => null,
    ]);
    $res2->assertCreated()
        ->assertJsonPath('data.category', null);
});

test('note category compatibility with folder', function () {
    $user = User::factory()->create();
    $catFolder = Folder::factory()->create([
        'user_id' => $user->id,
        'name' => 'Quest Folder',
        'category' => 'catatan',
    ]);
    $freeFolder = Folder::factory()->create([
        'user_id' => $user->id,
        'name' => 'Universal Folder',
        'category' => null,
    ]);

    // 1. Note matching folder category -> allowed
    $noteMatch = $this->actingAs($user)->postJson(route('notes.store'), [
        'title' => 'Matching Note',
        'category' => 'catatan',
        'folder_id' => $catFolder->id,
    ]);
    $noteMatch->assertCreated();

    // 2. Note with mismatched category -> rejected
    $noteMismatch = $this->actingAs($user)->postJson(route('notes.store'), [
        'title' => 'Mismatched Note',
        'category' => 'jurnal',
        'folder_id' => $catFolder->id,
    ]);
    $noteMismatch->assertUnprocessable()
        ->assertJsonValidationErrors('folder_id');

    // 3. Any note can enter a folder without category
    $noteInFree = $this->actingAs($user)->postJson(route('notes.store'), [
        'title' => 'Free Note',
        'category' => 'jurnal',
        'folder_id' => $freeFolder->id,
    ]);
    $noteInFree->assertCreated();

    // 4. Moving an existing mismatched note into categorized folder -> rejected
    $createdNote = $noteInFree->json('data.id');
    $this->actingAs($user)->putJson(route('notes.update', $createdNote), [
        'folder_id' => $catFolder->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('folder_id');
});

test('subfolder category must be compatible with parent folder category', function () {
    $user = User::factory()->create();
    $parentCat = Folder::factory()->create([
        'user_id' => $user->id,
        'name' => 'Parent With Category',
        'category' => 'catatan',
    ]);

    // Compatible subfolder (same category)
    $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Child Same Cat',
        'category' => 'catatan',
        'parent_id' => $parentCat->id,
    ])->assertCreated();

    // Incompatible subfolder (different category) -> rejected
    $this->actingAs($user)->postJson(route('api.folders.store'), [
        'name' => 'Child Other Cat',
        'category' => 'jurnal',
        'parent_id' => $parentCat->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('category');
});

test('folder category cannot be changed if existing notes conflict with the new category', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create([
        'user_id' => $user->id,
        'name' => 'Neutral Folder',
        'category' => null,
    ]);

    Note::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
        'category' => 'catatan',
    ]);

    // Updating folder category to 'catatan' matches existing notes -> allowed
    $this->actingAs($user)->putJson(route('api.folders.update', $folder), [
        'category' => 'catatan',
    ])->assertOk();

    // Updating folder category to 'jurnal' conflicts with existing note -> rejected
    $this->actingAs($user)->putJson(route('api.folders.update', $folder), [
        'category' => 'jurnal',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('category');
});
