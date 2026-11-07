<?php

use App\Models\Note;
use App\Models\User;
use App\Services\NoteChunkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| BLACK BOX TESTING: User Scenarios, External API Contracts & End-to-End Flows
|--------------------------------------------------------------------------
*/

test('black-box: user loads studio dashboard and receives complete responsive interface', function () {
    $note = Note::factory()->create(['title' => 'Ancient Grimoire Entry']);

    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('NOTENUX')
        ->assertSee('STUDIO v3.0')
        ->assertSee('Ancient Grimoire Entry')
        ->assertSee('primary-nav-sidebar')
        ->assertSee('notes-explorer-sidebar')
        ->assertSee('editor-zoom-wrapper')
        ->assertSee('toast-container')
        ->assertSee('font-awesome', false);
});

test('black-box: complete user scroll lifecycle flow (create, read, update, pin, favorite, clone, delete)', function () {
    // 1. Create Note
    $createPayload = [
        'title' => 'Quest: Finding the Lost Sapphire',
        'category' => 'quest',
        'content' => '<h2>Chapter 1</h2><p>The journey begins in the Misty Valleys.</p>',
        'font_family' => 'pixel',
        'font_size' => '16px',
        'theme_color' => 'blue',
    ];
    $createRes = $this->postJson('/api/notes', $createPayload);
    $createRes->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Quest: Finding the Lost Sapphire');

    $noteId = $createRes->json('data.id');
    expect($noteId)->not->toBeNull();

    // 2. Read Note
    $readRes = $this->getJson("/api/notes/{$noteId}");
    $readRes->assertStatus(200)
        ->assertJsonPath('data.id', $noteId)
        ->assertJsonPath('data.title', 'Quest: Finding the Lost Sapphire');

    // 3. Update Note
    $updatePayload = [
        'title' => 'Quest: The Sapphire Chamber',
        'category' => 'strategy',
        'content' => '<h2>Chapter 2</h2><p>We defeated the stone guardian and retrieved the jewel.</p>',
        'font_family' => 'mono',
    ];
    $updateRes = $this->putJson("/api/notes/{$noteId}", $updatePayload);
    $updateRes->assertStatus(200)
        ->assertJsonPath('data.title', 'Quest: The Sapphire Chamber')
        ->assertJsonPath('data.category', 'strategy')
        ->assertJsonPath('data.font_family', 'mono');

    // 4. Pin Note
    $pinRes = $this->postJson("/api/notes/{$noteId}/pin");
    $pinRes->assertStatus(200)->assertJsonPath('is_pinned', true);

    // 5. Favorite Note
    $favRes = $this->postJson("/api/notes/{$noteId}/favorite");
    $favRes->assertStatus(200)->assertJsonPath('is_favorite', true);

    // 6. Duplicate Note
    $dupRes = $this->postJson("/api/notes/{$noteId}/duplicate");
    $dupRes->assertStatus(201)
        ->assertJsonPath('data.title', 'Quest: The Sapphire Chamber (Copy)');
    $cloneId = $dupRes->json('data.id');

    // 7. Delete Original Note
    $delRes = $this->deleteJson("/api/notes/{$noteId}");
    $delRes->assertStatus(200);

    // 8. Verify Original is Gone
    $checkRes = $this->getJson("/api/notes/{$noteId}");
    $checkRes->assertStatus(404);

    // Clone should still exist independently
    $cloneRes = $this->getJson("/api/notes/{$cloneId}");
    $cloneRes->assertStatus(200);
});

test('black-box: user searches notes by title and content keyword', function () {
    Note::factory()->create(['title' => 'Dragon Slayer Quest', 'content' => '<p>Slay the red beast</p>']);
    Note::factory()->create(['title' => 'Arcane Shield Lore', 'content' => '<p>Protects against Dragon breath</p>']);
    Note::factory()->create(['title' => 'Tavern Songs', 'content' => '<p>Relaxing melody</p>']);

    $response = $this->getJson('/api/notes?q=Dragon');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $results = $response->json('data');
    expect(count($results))->toBe(2);
});

test('black-box: user filters notes by category and sort order', function () {
    Note::factory()->create(['title' => 'B Note', 'category' => 'spellbook', 'word_count' => 100, 'is_pinned' => false]);
    Note::factory()->create(['title' => 'A Note', 'category' => 'spellbook', 'word_count' => 500, 'is_pinned' => false]);
    Note::factory()->create(['title' => 'C Note', 'category' => 'quest', 'word_count' => 300, 'is_pinned' => false]);

    // Filter category
    $resCat = $this->getJson('/api/notes?category=spellbook');
    $resCat->assertStatus(200);
    expect(count($resCat->json('data')))->toBe(2);

    // Sort word_count_desc
    $resSort = $this->getJson('/api/notes?sort=word_count_desc');
    $resSort->assertStatus(200);
    $data = $resSort->json('data');
    expect($data[0]['word_count'])->toBeGreaterThanOrEqual($data[1]['word_count']);

    // Sort title_asc
    $resTitle = $this->getJson('/api/notes?sort=title_asc');
    $resTitle->assertStatus(200);
    $titles = array_column($resTitle->json('data'), 'title');
    $sortedTitles = $titles;
    sort($sortedTitles);
    expect($titles)->toBe($sortedTitles);
});

test('black-box: user inspects database chunks and verifies identity keys', function () {
    $note = Note::factory()->create([
        'title' => 'Chunked Scroll',
        'content' => '<h2>Section 1</h2><p>Part one content.</p><h2>Section 2</h2><p>Part two content.</p>',
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    $response = $this->getJson("/api/notes/{$note->id}/chunks");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('note_id', $note->id)
        ->assertJsonStructure([
            'status',
            'note_id',
            'total_chunks',
            'total_bytes',
            'chunks' => [
                '*' => ['id', 'chunk_uid', 'chunk_index', 'chunk_type', 'byte_size', 'checksum'],
            ],
        ]);

    $chunks = $response->json('chunks');
    foreach ($chunks as $c) {
        expect($c['chunk_uid'])->toStartWith('chk_')
            ->and($c['byte_size'])->toBeGreaterThan(0);
    }
});

test('black-box: player stats reflect real-time user activity telemetry', function () {
    Note::factory()->count(2)->create(['word_count' => 200, 'xp' => 350]);

    $response = $this->getJson('/api/player-stats');

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'status',
            'data' => [
                'total_notes',
                'total_words',
                'total_chunks',
                'total_xp',
                'level',
                'rank_title',
                'rank_badge',
                'mana',
                'progress_percent',
                'next_level_xp',
            ],
        ]);

    expect($response->json('data.total_notes'))->toBe(2)
        ->and($response->json('data.total_words'))->toBe(400);
});

test('black-box: user audits note cryptographic integrity via api endpoint', function () {
    $note = Note::factory()->create([
        'title' => 'Audit Scroll',
        'content' => '<h2>Integrity Chamber</h2><p>Cryptographically secure text storage.</p>',
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    $response = $this->getJson("/api/notes/{$note->id}/chunks/verify");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.corrupted_chunks', 0)
        ->assertJsonStructure([
            'status',
            'note_id',
            'note_uuid',
            'data' => [
                'status',
                'verified',
                'total_chunks',
                'valid_chunks',
                'corrupted_chunks',
                'details' => [
                    '*' => ['chunk_uid', 'chunk_index', 'is_valid', 'checksum_matches', 'bytes_match'],
                ],
            ],
        ]);
});

test('black-box: user updates individual chunk via api and prevents cross-note chunk tampering', function () {
    $noteA = Note::factory()->create(['title' => 'Scroll A', 'content' => '<p>Alpha content</p>']);
    $noteB = Note::factory()->create(['title' => 'Scroll B', 'content' => '<p>Beta content</p>']);

    $service = app(NoteChunkService::class);
    $service->syncChunks($noteA, $noteA->content);
    $service->syncChunks($noteB, $noteB->content);

    $chunkA = $noteA->chunks()->first();
    $chunkB = $noteB->chunks()->first();

    // 1. Valid update to chunk A on note A
    $updateRes = $this->putJson("/api/notes/{$noteA->id}/chunks/{$chunkA->id}", [
        'content' => '<p>Enhanced Alpha Content</p>',
    ]);
    $updateRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.chunk.content', '<p>Enhanced Alpha Content</p>');

    // 2. Reject mismatched note and chunk
    $mismatchRes = $this->putJson("/api/notes/{$noteA->id}/chunks/{$chunkB->id}", [
        'content' => '<p>Hacked content</p>',
    ]);
    $mismatchRes->assertStatus(404)
        ->assertJsonPath('status', 'error');

    // 3. Validation rejection on empty content
    $invalidRes = $this->putJson("/api/notes/{$noteA->id}/chunks/{$chunkA->id}", [
        'content' => '',
    ]);
    $invalidRes->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

test('black-box: user imports markdown scroll via uploaded file or raw payload', function () {
    // 1. Import via uploaded file
    $file = UploadedFile::fake()->createWithContent(
        'campaign_lore.md',
        "# The Northern Citadel\n\nGuarded by Frost Wyrms.\n\n| Level | Threat |\n|---|---|\n| 50 | Mythic |"
    );

    $fileRes = $this->postJson('/api/notes/import', [
        'file' => $file,
        'category' => 'lore',
    ]);

    $fileRes->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'The Northern Citadel')
        ->assertJsonPath('data.category', 'lore');

    expect($fileRes->json('data.chunks'))->not->toBeEmpty();

    // 2. Import via raw text payload
    $rawRes = $this->postJson('/api/notes/import', [
        'title' => 'Custom Strategy Guide',
        'content' => "# Dragon Tactics\n\nAim for the wings first.",
        'category' => 'strategy',
    ]);

    $rawRes->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Custom Strategy Guide')
        ->assertJsonPath('data.category', 'strategy');
});

test('black-box: user deletes individual shard via api and note reassembles automatically', function () {
    $note = Note::factory()->create([
        'title' => 'Shard Deletion Test',
        'content' => '<h2>Part A</h2><p>Content A</p><h2>Part B</h2><p>Content B</p>',
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    $chunkToDelete = $note->chunks()->first();

    $response = $this->deleteJson("/api/notes/{$note->id}/chunks/{$chunkToDelete->id}");

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.remaining_chunks', $note->chunks()->count());

    // Shard should no longer exist
    expect($note->chunks()->where('id', $chunkToDelete->id)->exists())->toBeFalse();
});

test('black-box: user reorders shards via api and verify new indices', function () {
    $note = Note::factory()->create([
        'title' => 'Reorder Test Scroll',
        'content' => '<h2>1. Alpha</h2><p>Body 1</p><h2>2. Beta</h2><p>Body 2</p>',
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    $chunks = $note->chunks()->orderBy('chunk_index', 'asc')->get();
    $reversedIds = $chunks->pluck('id')->reverse()->values()->all();

    $response = $this->postJson("/api/notes/{$note->id}/chunks/reorder", [
        'order' => $reversedIds,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $newOrderChunks = $response->json('data.chunks');
    expect($newOrderChunks[0]['id'])->toBe($reversedIds[0]);
});

test('black-box: system avoids excessive data load on list while seamlessly delivering full content and chunks on demand', function () {
    $note = Note::factory()->create([
        'title' => 'Performance Partitioned Scroll',
        'content' => '<h2>Performance Test</h2><p>Content loaded on-demand to preserve low memory footprint.</p>',
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    // 1. List query does not transmit monolithic content
    $listRes = $this->getJson('/api/notes');
    $listRes->assertOk();
    $found = collect($listRes->json('data'))->firstWhere('id', $note->id);
    expect($found)->not->toBeNull()
        ->and(array_key_exists('content', $found))->toBeFalse();

    // 2. Fetching individual note delivers complete content and its chunks
    $singleRes = $this->getJson("/api/notes/{$note->id}");
    $singleRes->assertOk()
        ->assertJsonPath('data.title', 'Performance Partitioned Scroll')
        ->assertJsonStructure([
            'status',
            'data' => [
                'id',
                'title',
                'content',
                'chunks' => [
                    '*' => ['id', 'chunk_uid', 'chunk_index', 'chunk_type', 'byte_size', 'checksum'],
                ],
            ],
        ]);
});

test('black-box: studio view features clean command deck, semantic toolbar clusters, and visual delete modal', function () {
    $note = Note::factory()->create([
        'title' => 'Ancient Quest Scroll of Legend',
        'category' => 'quest',
    ]);

    $response = $this->get('/');
    $response->assertOk()
        ->assertSee('note-title-input')
        ->assertSee('note-category-select')
        ->assertSee('favorite-note-btn')
        ->assertSee('pin-note-btn')
        ->assertSee('duplicate-note-btn')
        ->assertSee('export-note-btn')
        ->assertSee('zen-mode-btn')
        ->assertSee('archive-note-btn')
        ->assertSee('delete-note-btn')
        ->assertSee('save-now-btn')
        ->assertSee('delete-confirm-modal')
        ->assertSee('delete-modal-scroll-title')
        ->assertSee('cancel-delete-modal-btn')
        ->assertSee('confirm-delete-modal-btn')
        ->assertSee('BANISH SCROLL CONFIRMATION');
});

test('black-box: user filters notes by pinned_only and favorites_only', function () {
    Note::factory()->create(['title' => 'Pinned Only Note', 'is_pinned' => true, 'is_favorite' => false]);
    Note::factory()->create(['title' => 'Favorite Only Note', 'is_pinned' => false, 'is_favorite' => true]);
    Note::factory()->create(['title' => 'Both Pinned & Fav', 'is_pinned' => true, 'is_favorite' => true]);
    Note::factory()->create(['title' => 'Normal Note', 'is_pinned' => false, 'is_favorite' => false]);

    // Pinned only filter
    $pinnedRes = $this->getJson('/api/notes?pinned_only=1');
    $pinnedRes->assertOk();
    $pinnedData = $pinnedRes->json('data');
    expect(count($pinnedData))->toBe(2);
    foreach ($pinnedData as $n) {
        expect((bool) $n['is_pinned'])->toBeTrue();
    }

    // Favorites only filter
    $favRes = $this->getJson('/api/notes?favorites_only=1');
    $favRes->assertOk();
    $favData = $favRes->json('data');
    expect(count($favData))->toBe(2);
    foreach ($favData as $n) {
        expect((bool) $n['is_favorite'])->toBeTrue();
    }
});

test('black-box: complete personalized category journey with automatic note preservation', function () {
    $user = User::factory()->create();

    // 1. Forge new category
    $createCatRes = $this->actingAs($user)->postJson('/api/categories', [
        'name' => 'Arcane Invocations',
        'slug' => 'arcane-invocations',
        'color' => 'purple',
        'icon' => 'fa-solid fa-wand-magic-sparkles',
        'description' => 'Chants and ancient runes.',
    ]);
    $createCatRes->assertStatus(201)
        ->assertJsonPath('data.slug', 'arcane-invocations');

    $catId = $createCatRes->json('data.id');

    // 2. Inscribe note in this category
    $createNoteRes = $this->postJson('/api/notes', [
        'title' => 'Frostbolt Spell Formula',
        'category' => 'arcane-invocations',
        'content' => '<h2>Frostbolt</h2><p>Inflicts cold damage.</p>',
    ]);
    $createNoteRes->assertStatus(201);
    $noteId = $createNoteRes->json('data.id');

    // 3. Update category name and slug
    $updateCatRes = $this->actingAs($user)->putJson("/api/categories/{$catId}", [
        'name' => 'Grand Invocations',
        'slug' => 'grand-invocations',
        'color' => 'cyan',
    ]);
    $updateCatRes->assertOk()
        ->assertJsonPath('data.slug', 'grand-invocations');

    // Note should have its category updated automatically to match new slug
    $checkNoteRes = $this->getJson("/api/notes/{$noteId}");
    $checkNoteRes->assertOk()
        ->assertJsonPath('data.category', 'grand-invocations');

    // 4. Delete the category
    $deleteCatRes = $this->actingAs($user)->deleteJson("/api/categories/{$catId}");
    $deleteCatRes->assertOk();

    // 5. Verify the category is gone, but note is preserved safely in fallback
    $this->assertDatabaseMissing('categories', ['id' => $catId]);
    $preservationNote = Note::find($noteId);
    expect($preservationNote)->not->toBeNull()
        ->and($preservationNote->category)->not->toBe('grand-invocations');
});

test('black-box: user exports scroll in markdown, html, and plain text formats with valid headers', function () {
    $note = Note::factory()->create([
        'title' => 'Legendary Expedition Log',
        'category' => 'quest',
        'content' => '<h2>Chapter 1: The Mountain Gate</h2><p>The gates swung wide open as dawn broke.</p>',
        'word_count' => 15,
        'xp' => 45,
    ]);

    // 1. Markdown Export
    $mdRes = $this->get("/api/notes/{$note->id}/export/md");
    $mdRes->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="legendary-expedition-log.md"')
        ->assertSee('# Legendary Expedition Log')
        ->assertSee('## Chapter 1: The Mountain Gate');

    // 2. HTML Export
    $htmlRes = $this->get("/api/notes/{$note->id}/export/html");
    $htmlRes->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="legendary-expedition-log.html"')
        ->assertSee('<!DOCTYPE html>', false)
        ->assertSee('<h1>Legendary Expedition Log</h1>', false);

    // 3. Plain Text Export
    $txtRes = $this->get("/api/notes/{$note->id}/export/txt");
    $txtRes->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="legendary-expedition-log.txt"')
        ->assertSee('TITLE: Legendary Expedition Log')
        ->assertSee('The gates swung wide open as dawn broke.');
});
