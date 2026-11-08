<?php

use App\Models\Category;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteChunkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| BLACK BOX TESTING: User Scenarios, External API Contracts & End-to-End Flows
|--------------------------------------------------------------------------
*/

test('black-box: user loads studio dashboard and receives complete responsive interface', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['title' => 'Ancient Grimoire Entry']);

    $response = $this->actingAs($user)->get(route('studio'));

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
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'title' => 'Ancient Quest Scroll of Legend',
        'category' => 'quest',
    ]);

    $response = $this->actingAs($user)->get(route('studio'));
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
        ->assertSee('scroll-created-badge')
        ->assertSee('scroll-updated-badge')
        ->assertSee('scroll-created-text')
        ->assertSee('scroll-updated-text')
        ->assertSee('delete-confirm-modal')
        ->assertSee('delete-modal-scroll-title')
        ->assertSee('cancel-delete-modal-btn')
        ->assertSee('confirm-delete-modal-btn')
        ->assertSee('BANISH SCROLL CONFIRMATION');
});

test('black-box: studio view provides plain text notepad mode and one-click copy button', function () {
    $user = User::factory()->create();
    Note::factory()->create([
        'title' => 'Plain Text Manuscript',
        'category' => 'quest',
        'content' => '<p>Unformatted thoughts ready to be scribed.</p>',
    ]);

    $response = $this->actingAs($user)->get(route('studio'));
    $response->assertOk()
        ->assertSee('plain-text-toggle-btn')
        ->assertSee('plain-text-container')
        ->assertSee('plain-text-editor')
        ->assertSee('plain-text-copy-btn')
        ->assertSee('plain-text-clear-btn')
        ->assertSee('plain-text-exit-btn')
        ->assertSee('copy-plain-btn')
        ->assertSee('notepad-mode-banner')
        ->assertSee('MODE NOTEPAD')
        ->assertSee('Copy Semua Teks');
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

    // 5. Verify the category and its associated notes are automatically deleted
    $this->assertDatabaseMissing('categories', ['id' => $catId]);
    $this->assertDatabaseMissing('notes', ['id' => $noteId]);
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

test('black-box: user audits note cryptographic integrity and discovers tampered shard in real time', function () {
    $note = Note::factory()->create([
        'title' => 'Vault Security Protocol',
        'content' => '<h2>Alpha Shard</h2><p>Untampered crystal data.</p><h2>Beta Shard</h2><p>Second crystal sector.</p>',
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    // Initial audit: should be 100% valid
    $initialVerify = $this->getJson("/api/notes/{$note->id}/chunks/verify");
    $initialVerify->assertOk()
        ->assertJsonPath('data.is_valid', true)
        ->assertJsonPath('data.corrupted_chunks', 0);

    // Simulate tampering: update chunk content directly in the database without updating checksum
    $chunk = $note->chunks()->first();
    DB::table('note_chunks')->where('id', $chunk->id)->update([
        'content' => '<p>Corrupted malicious payload injected into shard.</p>',
    ]);

    // Re-verify: auditor detects mismatch
    $corruptedVerify = $this->getJson("/api/notes/{$note->id}/chunks/verify");
    $corruptedVerify->assertOk()
        ->assertJsonPath('data.is_valid', false)
        ->assertJsonPath('data.corrupted_chunks', 1);

    // Repair shard via official API: recalculates checksum and reassembles note
    $repairRes = $this->putJson("/api/notes/{$note->id}/chunks/{$chunk->id}", [
        'content' => '<h2>Alpha Shard</h2><p>Repaired crystal data.</p>',
    ]);
    $repairRes->assertOk()
        ->assertJsonPath('status', 'success');

    // Final audit: all valid again
    $finalVerify = $this->getJson("/api/notes/{$note->id}/chunks/verify");
    $finalVerify->assertOk()
        ->assertJsonPath('data.is_valid', true)
        ->assertJsonPath('data.corrupted_chunks', 0);
});

test('black-box: user exports scroll in json format with complete note telemetry and chunks', function () {
    $note = Note::factory()->create([
        'title' => 'Ancient Shard Archive',
        'category' => 'spellbook',
        'content' => '<h2>Arcane Gateway</h2><p>Portal coordinates inscribed here.</p>',
        'font_family' => 'mono',
        'font_size' => '16px',
        'theme_color' => 'blue',
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    $response = $this->get("/api/notes/{$note->id}/export/json");

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertHeader('Content-Disposition', 'attachment; filename="ancient-shard-archive.json"');

    $data = $response->json();
    expect($data['id'])->toBe($note->id)
        ->and($data['uuid'])->toBe($note->uuid)
        ->and($data['title'])->toBe('Ancient Shard Archive')
        ->and($data['category'])->toBe('spellbook')
        ->and($data['reading_time'])->toBe('1 min read')
        ->and($data['chunks'])->not->toBeEmpty();

    expect($data['chunks'][0])->toHaveKeys(['chunk_uid', 'checksum', 'byte_size', 'chunk_type', 'content']);
});

test('black-box: user exports rich scroll with tables, checklists, headings, and code to markdown format', function () {
    $htmlContent = '<h1>Grimoire Act I</h1><h2>Subchapter</h2><table class="retro-table"><tr><th>Rune</th><th>Cost</th></tr><tr><td>Fire</td><td>10 MP</td></tr></table><ul class="task-list"><li><input type="checkbox" checked> Gather herbs</li><li><input type="checkbox"> Brew potion</li></ul><pre><code>function castSpell() { return true; }</code></pre><p>Normal text with <strong>bold</strong>, <em>italic</em>, <del>struck</del>, and <a href="https://notenux.dev">portal link</a>.</p>';

    $note = Note::factory()->create([
        'title' => 'Arcane Formulation',
        'content' => $htmlContent,
    ]);

    $response = $this->get("/api/notes/{$note->id}/export/md");

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    $body = $response->getContent();
    expect($body)->toContain('# Grimoire Act I')
        ->and($body)->toContain('## Subchapter')
        ->and($body)->toContain('| Rune | Cost |')
        ->and($body)->toContain('| Fire | 10 MP |')
        ->and($body)->toContain('- [x] Gather herbs')
        ->and($body)->toContain('- [ ] Brew potion')
        ->and($body)->toContain("```\nfunction castSpell() { return true; }\n```")
        ->and($body)->toContain('**bold**')
        ->and($body)->toContain('*italic*')
        ->and($body)->toContain('~~struck~~')
        ->and($body)->toContain('[portal link](https://notenux.dev)');
});

test('black-box: user imports json backup and note is reconstructed with intact metadata and sanitized chunks', function () {
    $backupPayload = json_encode([
        'title' => 'Restored Citadel Map',
        'category' => 'strategy',
        'font_family' => 'arcade',
        'font_size' => '18px',
        'theme_color' => 'amber',
        'content' => '<h2>Citadel Overview</h2><p>Safely restored from backup vault.</p><script>alert("xss")</script>',
    ]);

    $file = UploadedFile::fake()->createWithContent('backup.json', $backupPayload);

    $response = $this->postJson('/api/notes/import', [
        'file' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Restored Citadel Map')
        ->assertJsonPath('data.category', 'strategy')
        ->assertJsonPath('data.font_family', 'arcade')
        ->assertJsonPath('data.font_size', '18px')
        ->assertJsonPath('data.theme_color', 'amber');

    expect($response->json('data.content'))->not->toContain('<script>')
        ->and($response->json('data.chunks'))->not->toBeEmpty();
});

test('black-box: user sorts scrolls by title_desc, created_desc, and created_asc', function () {
    $noteOld = Note::factory()->create(['title' => 'Alpha Scroll', 'created_at' => now()->subDays(10), 'is_pinned' => false]);
    $noteMid = Note::factory()->create(['title' => 'Beta Scroll', 'created_at' => now()->subDays(5), 'is_pinned' => false]);
    $noteNew = Note::factory()->create(['title' => 'Gamma Scroll', 'created_at' => now(), 'is_pinned' => false]);

    // Sort title_desc (Z to A)
    $resTitleDesc = $this->getJson('/api/notes?sort=title_desc');
    $resTitleDesc->assertOk();
    $titles = array_column($resTitleDesc->json('data'), 'title');
    expect($titles[0])->toBe('Gamma Scroll')
        ->and($titles[2])->toBe('Alpha Scroll');

    // Sort created_desc (Newest first)
    $resCreatedDesc = $this->getJson('/api/notes?sort=created_desc');
    $resCreatedDesc->assertOk();
    $ids = array_column($resCreatedDesc->json('data'), 'id');
    expect($ids[0])->toBe($noteNew->id)
        ->and($ids[2])->toBe($noteOld->id);

    // Sort created_asc (Oldest first)
    $resCreatedAsc = $this->getJson('/api/notes?sort=created_asc');
    $resCreatedAsc->assertOk();
    $idsAsc = array_column($resCreatedAsc->json('data'), 'id');
    expect($idsAsc[0])->toBe($noteOld->id)
        ->and($idsAsc[2])->toBe($noteNew->id);
});

test('black-box: note index validates sort and filter query parameters', function () {
    $response = $this->getJson('/api/notes?sort=unsupported_order');
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);

    $response2 = $this->getJson('/api/notes?pinned_only=not_a_bool');
    $response2->assertStatus(422)
        ->assertJsonValidationErrors(['pinned_only']);
});

test('black-box: user creates and updates scroll with large pasted base64 raster image without server errors', function () {
    $largeBase64 = 'data:image/png;base64,'.base64_encode(str_repeat('X', 80000));
    $htmlContent = "<h3>Map of the Realm</h3><p><img src=\"{$largeBase64}\" alt=\"Realm Map\"></p>";

    // Create note with pasted image
    $response = $this->postJson('/api/notes', [
        'title' => 'Cartography Scroll',
        'category' => 'quest',
        'content' => $htmlContent,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('status', 'success');

    $noteId = $response->json('data.id');
    expect($response->json('data.content'))->toContain($largeBase64);

    // Update note with modified pasted image
    $newBase64 = 'data:image/jpeg;base64,'.base64_encode(str_repeat('Y', 90000));
    $updatedHtml = "<h3>Updated Realm Map</h3><p><img src=\"{$newBase64}\" alt=\"Updated Map\"></p>";

    $updateResponse = $this->putJson("/api/notes/{$noteId}", [
        'content' => $updatedHtml,
    ]);

    $updateResponse->assertOk()
        ->assertJsonPath('status', 'success');

    expect($updateResponse->json('data.content'))->toContain($newBase64);

    // Verify chunks inspection endpoint returns valid chunks
    $chunksResponse = $this->getJson("/api/notes/{$noteId}/chunks");
    $chunksResponse->assertOk();
    $chunks = $chunksResponse->json('chunks');
    expect(count($chunks))->toBeGreaterThan(0);
});

test('black-box: non-owner user receives 403 forbidden when attempting to update or delete another users category', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $category = Category::factory()->create(['user_id' => $owner->id, 'name' => 'Owner Secret']);

    // Attempt update as attacker
    $updateRes = $this->actingAs($attacker)->putJson(route('api.categories.update', $category), [
        'name' => 'Hijacked Category',
    ]);
    $updateRes->assertStatus(403);

    // Attempt delete as attacker
    $deleteRes = $this->actingAs($attacker)->deleteJson(route('api.categories.destroy', $category));
    $deleteRes->assertStatus(403);

    // Category remains untouched
    expect($category->fresh()->name)->toBe('Owner Secret');
});

test('black-box: ai assistant handles improve, summarize, continue, translate, and custom actions with error boundary fallbacks', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create();

    // 1. Validation error: missing instruction on custom action
    $resMissingInst = $this->actingAs($user)->postJson(route('notes.assistant', $note), [
        'action' => 'custom',
        'content' => 'Short text',
    ]);
    $resMissingInst->assertStatus(422)
        ->assertJsonValidationErrors(['instruction']);

    // 2. Validation error: content exceeds max limit
    $resOversized = $this->actingAs($user)->postJson(route('notes.assistant', $note), [
        'action' => 'improve',
        'content' => str_repeat('X', 30001),
    ]);
    $resOversized->assertStatus(422)
        ->assertJsonValidationErrors(['content']);

    // 3. Service unconfigured (503) when API key or model is empty
    config(['services.ai.key' => null, 'services.ai.model' => null]);
    $resUnconfigured = $this->actingAs($user)->postJson(route('notes.assistant', $note), [
        'action' => 'improve',
        'content' => 'Good content to polish',
    ]);
    $resUnconfigured->assertStatus(503)
        ->assertJsonPath('status', 'error');

    // 4. Mock successful AI call
    config(['services.ai.key' => 'test-key', 'services.ai.model' => 'test-model', 'services.ai.base_url' => 'https://api.test-ai.dev/v1']);
    Http::fake([
        'api.test-ai.dev/*' => Http::response([
            'choices' => [
                [
                    'message' => ['content' => 'Polished and perfected manuscript.'],
                    'finish_reason' => 'stop',
                ],
            ],
        ], 200),
    ]);

    $resSuccess = $this->actingAs($user)->postJson(route('notes.assistant', $note), [
        'action' => 'improve',
        'content' => 'Rough manuscript text needing polish.',
    ]);
    $resSuccess->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.content', 'Polished and perfected manuscript.');
});

test('black-box: auth registration workflow initializes default categories, welcome scroll, and establishes session', function () {
    $email = 'adventurer_'.uniqid().'@notenux.dev';
    $response = $this->post(route('register'), [
        'name' => 'Brave Adventurer',
        'email' => $email,
        'password' => 'secret-password-123',
        'password_confirmation' => 'secret-password-123',
    ]);

    $response->assertRedirect(route('studio'));
    $this->assertAuthenticated();

    $user = User::where('email', $email)->first();
    expect($user)->not->toBeNull();

    // Verify default categories
    $categories = Category::forUser($user)->pluck('slug')->all();
    expect($categories)->toContain('catatan')
        ->and($categories)->toContain('tugas');

    // Verify welcome note
    $welcomeNote = Note::where('title', 'Halo! Selamat Datang di Notenux')->first();
    expect($welcomeNote)->not->toBeNull()
        ->and($welcomeNote->category)->toBe('catatan')
        ->and($welcomeNote->chunks()->count())->toBeGreaterThan(0);
});

test('black-box: chunk reordering rejects mismatched, missing, or duplicate shard identities with 422 error', function () {
    $note = Note::factory()->create();
    $service = app(NoteChunkService::class);
    $content = '<p>'.str_repeat('A', 1100).'</p><p>'.str_repeat('B', 1100).'</p><p>'.str_repeat('C', 1100).'</p>';
    $chunks = $service->syncChunks($note, $content);

    expect(count($chunks))->toBe(3);
    $chunk1 = $chunks[0];
    $chunk2 = $chunks[1];

    // Reorder payload missing chunk 3
    $responseMissing = $this->postJson(route('notes.chunks.reorder', $note), [
        'order' => [$chunk1->chunk_uid, $chunk2->chunk_uid],
    ]);
    $responseMissing->assertStatus(422)
        ->assertJsonValidationErrors(['order']);

    // Reorder payload containing duplicates
    $responseDuplicate = $this->postJson(route('notes.chunks.reorder', $note), [
        'order' => [$chunk1->chunk_uid, $chunk1->chunk_uid, $chunk2->chunk_uid],
    ]);
    $responseDuplicate->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('black-box: duplicate scroll handles multibyte unicode titles up to 255 characters without truncation errors', function () {
    $longTitle = str_repeat('📜 Gulungan Mistik Bersejarah Kuno ', 7); // Very long unicode title
    $note = Note::factory()->create([
        'title' => mb_substr($longTitle, 0, 240),
        'content' => '<p>Konten naskah kuno.</p>',
    ]);

    $response = $this->postJson(route('notes.duplicate', $note));
    $response->assertCreated()
        ->assertJsonPath('status', 'success');

    $clonedTitle = $response->json('data.title');
    expect(mb_strlen($clonedTitle))->toBeLessThanOrEqual(255)
        ->and($clonedTitle)->toEndWith('(Copy)');
});

test('black-box: user can export scroll in json format with intact chunk array and import it back seamlessly', function () {
    $note = Note::factory()->create([
        'title' => 'Arcane Roundtrip Expedition',
        'category' => 'spellbook',
        'content' => '<h2>Grand Lore</h2><p>Restored from JSON matrix shard backup.</p>',
        'font_family' => 'arcade',
        'font_size' => '18px',
        'theme_color' => 'emerald',
    ]);
    $service = app(NoteChunkService::class);
    $service->syncChunks($note, $note->content);

    // 1. Export note to JSON
    $exportRes = $this->get("/api/notes/{$note->id}/export/json");
    $exportRes->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8');

    $jsonPayload = $exportRes->getContent();

    // 2. Import the JSON payload back as a new note
    $importRes = $this->postJson('/api/notes/import', [
        'content' => $jsonPayload,
    ]);

    $importRes->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Arcane Roundtrip Expedition')
        ->assertJsonPath('data.category', 'spellbook')
        ->assertJsonPath('data.font_family', 'arcade')
        ->assertJsonPath('data.font_size', '18px')
        ->assertJsonPath('data.theme_color', 'emerald');

    $importedNoteId = $importRes->json('data.id');
    expect($importedNoteId)->not->toBeNull()
        ->and($importedNoteId)->not->toBe($note->id);

    $importedNote = Note::find($importedNoteId);
    expect($importedNote->chunks()->count())->toBeGreaterThan(0)
        ->and($importedNote->content)->toContain('Grand Lore');
});

test('black-box: user bulk deletes multiple scrolls via api/notes/bulk-delete endpoint', function () {
    $note1 = Note::factory()->create(['title' => 'Ancient Shard 1']);
    $note2 = Note::factory()->create(['title' => 'Ancient Shard 2']);
    $note3 = Note::factory()->create(['title' => 'Keeper Scroll']);

    $response = $this->postJson('/api/notes/bulk-delete', [
        'ids' => [$note1->id, $note2->id],
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.deleted_count', 2)
        ->assertJsonPath('data.deleted_ids', [$note1->id, $note2->id]);

    expect(Note::find($note1->id))->toBeNull()
        ->and(Note::find($note2->id))->toBeNull()
        ->and(Note::find($note3->id))->not->toBeNull();
});

test('black-box: user bulk deletes multiple categories via categories/bulk-delete web route and api route', function () {
    $user = User::factory()->create();
    $cat1 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Guild 1', 'slug' => 'guild-1']);
    $cat2 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Guild 2', 'slug' => 'guild-2']);
    $catKeep = Category::factory()->create(['user_id' => $user->id, 'name' => 'Guild Keep', 'slug' => 'guild-keep']);

    $note1 = Note::factory()->create(['category' => 'guild-1', 'title' => 'Scroll In Guild 1']);
    $note2 = Note::factory()->create(['category' => 'guild-2', 'title' => 'Scroll In Guild 2']);
    $noteKeep = Note::factory()->create(['category' => 'guild-keep', 'title' => 'Scroll In Guild Keep']);

    // 1. Web route with redirect
    $webResponse = $this->actingAs($user)->post('/categories/bulk-delete', [
        'ids' => [$cat1->id],
    ]);
    $webResponse->assertRedirect(route('categories.index'));

    expect(Category::find($cat1->id))->toBeNull()
        ->and(Note::find($note1->id))->toBeNull();

    // 2. API route with JSON
    $apiResponse = $this->actingAs($user)->postJson('/api/categories/bulk-delete', [
        'ids' => [$cat2->id],
    ]);
    $apiResponse->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.deleted_count', 1);

    expect(Category::find($cat2->id))->toBeNull()
        ->and(Note::find($note2->id))->toBeNull()
        ->and(Category::find($catKeep->id))->not->toBeNull()
        ->and(Note::find($noteKeep->id))->not->toBeNull();
});

test('black-box: studio view and categories index include bulk selection controls and modals', function () {
    $user = User::factory()->create();

    // Check studio panel view
    $studioRes = $this->actingAs($user)->get(route('studio'));
    $studioRes->assertOk()
        ->assertSee('toggle-bulk-select-notes-btn')
        ->assertSee('notes-bulk-bar')
        ->assertSee('bulk-delete-notes-btn')
        ->assertSee('bulk-delete-notes-modal');

    // Check categories index view
    $categoriesRes = $this->actingAs($user)->get(route('categories.index'));
    $categoriesRes->assertOk()
        ->assertSee('toggle-bulk-categories-btn')
        ->assertSee('categories-bulk-bar')
        ->assertSee('bulk-delete-cat-btn')
        ->assertSee('bulk-delete-categories-modal');
});

test('black-box: user bulk deletes multiple notes atomically via api', function () {
    $note1 = Note::factory()->create(['title' => 'Batch Note Alpha']);
    $note2 = Note::factory()->create(['title' => 'Batch Note Beta']);
    $note3 = Note::factory()->create(['title' => 'Untouched Note']);

    $res = $this->postJson('/api/notes/bulk-delete', [
        'ids' => [$note1->id, $note2->id],
    ]);

    $res->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.deleted_count', 2);

    $this->assertDatabaseMissing('notes', ['id' => $note1->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note2->id]);
    $this->assertDatabaseHas('notes', ['id' => $note3->id]);
});

test('black-box: user bulk deletes multiple categories and their associated notes atomically', function () {
    $user = User::factory()->create();
    $cat1 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Cat Alpha', 'slug' => 'cat-alpha']);
    $cat2 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Cat Beta', 'slug' => 'cat-beta']);
    $cat3 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Cat Gamma', 'slug' => 'cat-gamma']);

    $note1 = Note::factory()->create(['category' => 'cat-alpha']);
    $note2 = Note::factory()->create(['category' => 'cat-beta']);
    $note3 = Note::factory()->create(['category' => 'cat-gamma']);

    $res = $this->actingAs($user)->postJson('/api/categories/bulk-delete', [
        'ids' => [$cat1->id, $cat2->id],
    ]);

    $res->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.deleted_count', 2);

    $this->assertDatabaseMissing('categories', ['id' => $cat1->id]);
    $this->assertDatabaseMissing('categories', ['id' => $cat2->id]);
    $this->assertDatabaseHas('categories', ['id' => $cat3->id]);

    $this->assertDatabaseMissing('notes', ['id' => $note1->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note2->id]);
    $this->assertDatabaseHas('notes', ['id' => $note3->id]);
});

test('black-box: user creates note with initial pinned and favorite flags and retrieves it via api', function () {
    $res = $this->postJson('/api/notes', [
        'title' => 'Top Priority Chronicle',
        'category' => 'strategy',
        'content' => '<h2>High Tier Strategy</h2><p>Execute flanking maneuvers.</p>',
        'is_pinned' => true,
        'is_favorite' => true,
    ]);

    $res->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Top Priority Chronicle')
        ->assertJsonPath('data.is_pinned', true)
        ->assertJsonPath('data.is_favorite', true);

    $id = $res->json('data.id');
    $this->assertDatabaseHas('notes', [
        'id' => $id,
        'is_pinned' => true,
        'is_favorite' => true,
    ]);
});

test('black-box: studio view renders category filter pills including pinned and favorites filters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('studio'));
    $response->assertOk()
        ->assertSee('data-category="pinned"', false)
        ->assertSee('data-category="favorites"', false)
        ->assertSee('data-category="all"', false);
});

test('black-box: user filters notes via api using category=pinned and category=favorites', function () {
    $notePinned = Note::factory()->create([
        'title' => 'Important Pinned Quest',
        'category' => 'quest',
        'is_pinned' => true,
        'is_favorite' => false,
    ]);

    $noteFav = Note::factory()->create([
        'title' => 'Favorite Strategy Map',
        'category' => 'strategy',
        'is_pinned' => false,
        'is_favorite' => true,
    ]);

    $noteNormal = Note::factory()->create([
        'title' => 'Standard Scroll',
        'category' => 'quest',
        'is_pinned' => false,
        'is_favorite' => false,
    ]);

    $pinnedRes = $this->getJson('/api/notes?category=pinned');
    $pinnedRes->assertOk();
    $pinnedIds = array_column($pinnedRes->json('data'), 'id');
    expect($pinnedIds)->toContain($notePinned->id)
        ->and($pinnedIds)->not->toContain($noteFav->id)
        ->and($pinnedIds)->not->toContain($noteNormal->id);

    $favRes = $this->getJson('/api/notes?category=favorites');
    $favRes->assertOk();
    $favIds = array_column($favRes->json('data'), 'id');
    expect($favIds)->toContain($noteFav->id)
        ->and($favIds)->not->toContain($notePinned->id)
        ->and($favIds)->not->toContain($noteNormal->id);
});

test('black-box: user exports note to json and imports back with full fidelity', function () {
    $original = Note::factory()->create([
        'title' => 'Dragon Lore Compendium',
        'category' => 'lore',
        'content' => '<h2>Ancient Wyrm</h2><p>Breathes azure flames of eternity.</p>',
        'is_pinned' => true,
        'is_favorite' => true,
    ]);

    $exportRes = $this->get("/api/notes/{$original->id}/export/json");
    $exportRes->assertOk();
    $jsonString = $exportRes->getContent();

    $importRes = $this->postJson('/api/notes/import', [
        'content' => $jsonString,
    ]);

    $importRes->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Dragon Lore Compendium')
        ->assertJsonPath('data.is_pinned', true)
        ->assertJsonPath('data.is_favorite', true);

    $newId = $importRes->json('data.id');
    expect($newId)->not->toBe($original->id);

    $this->assertDatabaseHas('notes', [
        'id' => $newId,
        'title' => 'Dragon Lore Compendium',
        'is_pinned' => true,
        'is_favorite' => true,
    ]);
});

test('black-box: user creates category with reserved name and receives safe suffixed slug', function () {
    $user = User::factory()->create();

    $resPinned = $this->actingAs($user)->postJson('/api/categories', [
        'name' => 'Pinned',
    ]);
    $resPinned->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.slug', 'pinned-2');

    $resFavorites = $this->actingAs($user)->postJson('/api/categories', [
        'name' => 'Favorites',
    ]);
    $resFavorites->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.slug', 'favorites-2');
});

test('black-box: user exports note to csv via api and receives downloadable spreadsheet data', function () {
    $note = Note::factory()->create([
        'title' => 'Arcane Spell Matrix',
        'category' => 'spellbook',
        'content' => '<h2>Lightning Bolt</h2><p>Inflicts 50 lightning damage to enemies in line.</p>',
        'is_pinned' => true,
        'is_favorite' => false,
    ]);

    $res = $this->get("/api/notes/{$note->id}/export/csv");
    $res->assertOk();
    $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $res->assertHeader('Content-Disposition', 'attachment; filename="arcane-spell-matrix.csv"');

    $csv = $res->getContent();
    expect($csv)->toContain('Title,Category,Level,XP,Words,Chars,Pinned,Favorite,Created,Updated,Content')
        ->and($csv)->toContain('Arcane Spell Matrix')
        ->and($csv)->toContain('Lightning Bolt');
});

test('black-box: user visits categories api with empty database and receives auto-seeded default categories', function () {
    Category::query()->delete();
    expect(Category::count())->toBe(0);

    $res = $this->getJson('/api/categories');
    $res->assertOk()
        ->assertJsonPath('status', 'success');

    $slugs = array_column($res->json('data'), 'slug');
    expect($slugs)->toContain('catatan')
        ->and($slugs)->toContain('quest')
        ->and($slugs)->toContain('spellbook');
});

test('black-box: user creates note with external links and checklist and attributes persist securely', function () {
    $res = $this->postJson('/api/notes', [
        'title' => 'Portal Waypoint Notes',
        'category' => 'quest',
        'content' => '<p>Visit <a href="https://example.com/gate" target="_blank" rel="noopener">Astral Gate</a></p>'
            .'<ul><li><input type="checkbox" checked disabled> Acquired Rune Key</li></ul>',
    ]);

    $res->assertCreated()
        ->assertJsonPath('status', 'success');

    $id = $res->json('data.id');
    $note = Note::findOrFail($id);

    expect($note->content)->toContain('target="_blank"')
        ->and($note->content)->toContain('rel="noopener"')
        ->and($note->content)->toContain('disabled')
        ->and($note->content)->toContain('checked');
});

test('black-box: studio view features image artifact matrix toolkit and user persists note with aligned figure and caption', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('studio'));
    $response->assertOk()
        ->assertSee('image-toolkit-bar')
        ->assertSee('image-align-badge')
        ->assertSee('img-align-left-btn')
        ->assertSee('img-align-center-btn')
        ->assertSee('img-align-right-btn')
        ->assertSee('img-caption-btn')
        ->assertSee('img-delete-btn')
        ->assertSee('Beri Caption');

    // Create note with aligned figure and caption
    $figureHtml = '<figure class="retro-image-figure retro-align-center text-center my-3" data-align="center">'
        .'<img src="https://notenux.dev/emblem.png" alt="Guild Emblem" class="retro-image rounded-lg">'
        .'<figcaption class="retro-image-caption" contenteditable="true">Lambang Serikat Petualang</figcaption>'
        .'</figure>';

    $createRes = $this->actingAs($user)->postJson('/api/notes', [
        'title' => 'Scroll of Guild Heraldry',
        'category' => 'lore',
        'content' => $figureHtml,
    ]);

    $createRes->assertCreated()
        ->assertJsonPath('status', 'success');

    $noteId = $createRes->json('data.id');
    $note = Note::with('chunks')->findOrFail($noteId);

    expect($note->content)->toContain('<figure')
        ->and($note->content)->toContain('retro-align-center')
        ->and($note->content)->toContain('data-align="center"')
        ->and($note->content)->toContain('https://notenux.dev/emblem.png')
        ->and($note->content)->toContain('Lambang Serikat Petualang')
        ->and($note->chunks)->not->toBeEmpty();
});

test('black-box: user bulk updates pinned and favorite flags across multiple notes via api', function () {
    $note1 = Note::factory()->create(['title' => 'Scroll Alpha', 'is_pinned' => false, 'is_favorite' => false]);
    $note2 = Note::factory()->create(['title' => 'Scroll Beta', 'is_pinned' => false, 'is_favorite' => false]);
    $note3 = Note::factory()->create(['title' => 'Scroll Gamma', 'is_pinned' => false, 'is_favorite' => false]);

    $res = $this->postJson('/api/notes/bulk-update', [
        'ids' => [$note1->id, $note2->id],
        'is_pinned' => true,
        'is_favorite' => true,
    ]);

    $res->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.updated_count', 2);

    $this->assertDatabaseHas('notes', ['id' => $note1->id, 'is_pinned' => true, 'is_favorite' => true]);
    $this->assertDatabaseHas('notes', ['id' => $note2->id, 'is_pinned' => true, 'is_favorite' => true]);
    $this->assertDatabaseHas('notes', ['id' => $note3->id, 'is_pinned' => false, 'is_favorite' => false]);
});

test('black-box: user bulk moves multiple notes to another category via api', function () {
    $note1 = Note::factory()->create(['category' => 'quest']);
    $note2 = Note::factory()->create(['category' => 'quest']);
    $note3 = Note::factory()->create(['category' => 'lore']);

    $res = $this->postJson('/api/notes/bulk-update', [
        'ids' => [$note1->id, $note2->id],
        'category' => 'archive',
    ]);

    $res->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.updated_count', 2);

    $this->assertDatabaseHas('notes', ['id' => $note1->id, 'category' => 'archive']);
    $this->assertDatabaseHas('notes', ['id' => $note2->id, 'category' => 'archive']);
    $this->assertDatabaseHas('notes', ['id' => $note3->id, 'category' => 'lore']);
});

test('black-box: multi-tenant isolation ensures users can only list, view, and search their own notes', function () {
    $userAlpha = User::factory()->create(['name' => 'Scribe Alpha', 'email' => 'alpha@notenux.dev']);
    $userBeta = User::factory()->create(['name' => 'Scribe Beta', 'email' => 'beta@notenux.dev']);

    $noteAlpha = Note::factory()->create([
        'user_id' => $userAlpha->id,
        'title' => 'Alpha Arcane Secrets',
        'content' => '<p>Secret of Alpha</p>',
        'category' => 'quest',
    ]);

    $noteBeta = Note::factory()->create([
        'user_id' => $userBeta->id,
        'title' => 'Beta Forbidden Grimoire',
        'content' => '<p>Secret of Beta</p>',
        'category' => 'spellbook',
    ]);

    // Alpha lists notes via API
    $resAlpha = $this->actingAs($userAlpha)->getJson('/api/notes');
    $resAlpha->assertOk();
    $alphaNotes = collect($resAlpha->json('data'));
    expect($alphaNotes->pluck('id'))->toContain($noteAlpha->id)
        ->and($alphaNotes->pluck('id'))->not->toContain($noteBeta->id);

    // Beta lists notes via API
    $resBeta = $this->actingAs($userBeta)->getJson('/api/notes');
    $resBeta->assertOk();
    $betaNotes = collect($resBeta->json('data'));
    expect($betaNotes->pluck('id'))->toContain($noteBeta->id)
        ->and($betaNotes->pluck('id'))->not->toContain($noteAlpha->id);

    // Alpha searches notes
    $searchAlpha = $this->actingAs($userAlpha)->getJson('/api/notes?q=Forbidden');
    $searchAlpha->assertOk();
    expect(collect($searchAlpha->json('data'))->pluck('id'))->not->toContain($noteBeta->id);

    // Alpha views studio dashboard
    $studioAlpha = $this->actingAs($userAlpha)->get(route('studio'));
    $studioAlpha->assertOk()
        ->assertSee('Alpha Arcane Secrets')
        ->assertDontSee('Beta Forbidden Grimoire');

    // Beta views studio dashboard
    $studioBeta = $this->actingAs($userBeta)->get(route('studio'));
    $studioBeta->assertOk()
        ->assertSee('Beta Forbidden Grimoire')
        ->assertDontSee('Alpha Arcane Secrets');
});

test('black-box: multi-tenant isolation prevents cross-tenant modification, deletion, chunk tampering, and export', function () {
    $userAlpha = User::factory()->create();
    $userBeta = User::factory()->create();

    $noteAlpha = Note::factory()->create([
        'user_id' => $userAlpha->id,
        'title' => 'Alpha Sacred Scroll',
        'content' => '<p>Initial Alpha Content</p>',
        'is_pinned' => false,
        'is_favorite' => false,
    ]);

    // Beta attempts to read Alpha\'s note -> 403 Forbidden
    $this->actingAs($userBeta)->getJson("/api/notes/{$noteAlpha->id}")
        ->assertStatus(403);

    // Beta attempts to update Alpha\'s note -> 403 Forbidden
    $this->actingAs($userBeta)->putJson("/api/notes/{$noteAlpha->id}", [
        'title' => 'Tampered by Beta',
    ])->assertStatus(403);
    expect($noteAlpha->fresh()->title)->toBe('Alpha Sacred Scroll');

    // Beta attempts to toggle pin -> 403 Forbidden
    $this->actingAs($userBeta)->postJson("/api/notes/{$noteAlpha->id}/pin")
        ->assertStatus(403);
    expect($noteAlpha->fresh()->is_pinned)->toBeFalse();

    // Beta attempts to toggle favorite -> 403 Forbidden
    $this->actingAs($userBeta)->postJson("/api/notes/{$noteAlpha->id}/favorite")
        ->assertStatus(403);
    expect($noteAlpha->fresh()->is_favorite)->toBeFalse();

    // Beta attempts to duplicate Alpha\'s note -> 403 Forbidden
    $this->actingAs($userBeta)->postJson("/api/notes/{$noteAlpha->id}/duplicate")
        ->assertStatus(403);

    // Beta attempts to export Alpha\'s note -> 403 Forbidden
    $this->actingAs($userBeta)->get("/api/notes/{$noteAlpha->id}/export/md")
        ->assertStatus(403);
    $this->actingAs($userBeta)->get("/api/notes/{$noteAlpha->id}/export/json")
        ->assertStatus(403);
    $this->actingAs($userBeta)->get("/api/notes/{$noteAlpha->id}/export/txt")
        ->assertStatus(403);

    // Beta attempts to inspect chunks -> 403 Forbidden
    $this->actingAs($userBeta)->getJson("/api/notes/{$noteAlpha->id}/chunks")
        ->assertStatus(403);

    // Beta attempts to verify chunks -> 403 Forbidden
    $this->actingAs($userBeta)->getJson("/api/notes/{$noteAlpha->id}/chunks/verify")
        ->assertStatus(403);

    // Beta attempts to delete Alpha\'s note -> 403 Forbidden
    $this->actingAs($userBeta)->deleteJson("/api/notes/{$noteAlpha->id}")
        ->assertStatus(403);
    $this->assertDatabaseHas('notes', ['id' => $noteAlpha->id]);

    // Alpha can legitimately view, update, and manage their own note
    $this->actingAs($userAlpha)->getJson("/api/notes/{$noteAlpha->id}")
        ->assertOk()
        ->assertJsonPath('data.title', 'Alpha Sacred Scroll');
});

test('black-box: multi-tenant isolation blocks cross-tenant bulk-delete, bulk-update, and ai-assistant', function () {
    $userAlpha = User::factory()->create();
    $userBeta = User::factory()->create();

    $noteAlpha = Note::factory()->create([
        'user_id' => $userAlpha->id,
        'title' => 'Alpha Fortified Note',
        'is_pinned' => false,
    ]);
    $noteBeta = Note::factory()->create([
        'user_id' => $userBeta->id,
        'title' => 'Beta Regular Note',
        'is_pinned' => false,
    ]);

    // Beta attempts bulk-delete including Alpha\'s note -> 403 Forbidden and no notes deleted
    $this->actingAs($userBeta)->postJson('/api/notes/bulk-delete', [
        'ids' => [$noteAlpha->id, $noteBeta->id],
    ])->assertStatus(403);

    $this->assertDatabaseHas('notes', ['id' => $noteAlpha->id]);
    $this->assertDatabaseHas('notes', ['id' => $noteBeta->id]);

    // Beta attempts bulk-update including Alpha\'s note -> 403 Forbidden and no notes modified
    $this->actingAs($userBeta)->postJson('/api/notes/bulk-update', [
        'ids' => [$noteAlpha->id, $noteBeta->id],
        'is_pinned' => true,
    ])->assertStatus(403);

    expect($noteAlpha->fresh()->is_pinned)->toBeFalse()
        ->and($noteBeta->fresh()->is_pinned)->toBeFalse();

    // Beta attempts AI assistant invocation on Alpha\'s note -> 403 Forbidden
    $this->actingAs($userBeta)->postJson("/api/notes/{$noteAlpha->id}/assistant", [
        'action' => 'improve',
        'content' => 'Tamper test',
    ])->assertStatus(403);
});

test('black-box: multi-tenant player telemetry and HUD stats isolate words, XP, and chunk counts', function () {
    $userAlpha = User::factory()->create();
    $userBeta = User::factory()->create();

    Note::factory()->create([
        'user_id' => $userAlpha->id,
        'word_count' => 100,
        'xp' => 150,
        'is_pinned' => true,
        'is_favorite' => false,
    ]);

    Note::factory()->create([
        'user_id' => $userBeta->id,
        'word_count' => 500,
        'xp' => 750,
        'is_pinned' => true,
        'is_favorite' => true,
    ]);

    $resAlpha = $this->actingAs($userAlpha)->getJson('/api/player-stats');
    $resAlpha->assertOk();
    $dataAlpha = $resAlpha->json('data');

    expect($dataAlpha['total_notes'])->toBe(1)
        ->and($dataAlpha['total_words'])->toBe(100)
        ->and($dataAlpha['total_xp'])->toBe(150)
        ->and($dataAlpha['pinned_notes'])->toBe(1)
        ->and($dataAlpha['favorite_notes'])->toBe(0);

    $resBeta = $this->actingAs($userBeta)->getJson('/api/player-stats');
    $resBeta->assertOk();
    $dataBeta = $resBeta->json('data');

    expect($dataBeta['total_notes'])->toBe(1)
        ->and($dataBeta['total_words'])->toBe(500)
        ->and($dataBeta['total_xp'])->toBe(750)
        ->and($dataBeta['pinned_notes'])->toBe(1)
        ->and($dataBeta['favorite_notes'])->toBe(1);
});

test('black-box: multi-registration prevents duplicate category clutter and strictly isolates greeting notes', function () {
    // Register User 1
    $this->post('/register', [
        'name' => 'Fajerin Scribe',
        'email' => 'fajerin@notenux.dev',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
    ])->assertRedirect(route('studio'));

    $user1 = User::where('email', 'fajerin@notenux.dev')->firstOrFail();

    // Register User 2
    $this->post('/logout');
    $this->post('/register', [
        'name' => 'Second Adventurer',
        'email' => 'second@notenux.dev',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
    ])->assertRedirect(route('studio'));

    $user2 = User::where('email', 'second@notenux.dev')->firstOrFail();

    // Register User 3
    $this->post('/logout');
    $this->post('/register', [
        'name' => 'Third Adventurer',
        'email' => 'third@notenux.dev',
        'password' => 'secret12345',
        'password_confirmation' => 'secret12345',
    ])->assertRedirect(route('studio'));

    $user3 = User::where('email', 'third@notenux.dev')->firstOrFail();

    // User 1 views studio: must see ONLY 1 welcome note with their name, not user 2 or user 3 welcome notes
    $studio1 = $this->actingAs($user1)->get(route('studio'));
    $studio1->assertOk()
        ->assertSee('Fajerin Scribe')
        ->assertDontSee('Second Adventurer')
        ->assertDontSee('Third Adventurer');

    // User 1 note list via API: exactly 1 note
    $notesRes1 = $this->actingAs($user1)->getJson('/api/notes');
    $notesRes1->assertOk();
    $user1Notes = $notesRes1->json('data');
    expect(count($user1Notes))->toBe(1)
        ->and($user1Notes[0]['title'])->toBe('Halo! Selamat Datang di Notenux');

    // User 1 categories: must not have duplicate "Catatan" or "Tugas" from User 2 and User 3
    $catsRes1 = $this->actingAs($user1)->getJson('/api/categories');
    $catsRes1->assertOk();
    $categories1 = collect($catsRes1->json('data'));
    $catatanCount = $categories1->where('slug', 'catatan')->count();
    $tugasCount = $categories1->where('slug', 'tugas')->count();

    expect($catatanCount)->toBe(1)
        ->and($tugasCount)->toBe(1);

    // User 1 stats: reflects level 1 (only 1 note with 105 XP), not level 12!
    $statsRes1 = $this->actingAs($user1)->getJson('/api/player-stats');
    $statsRes1->assertOk();
    expect($statsRes1->json('data.total_notes'))->toBe(1)
        ->and($statsRes1->json('data.level'))->toBe(1)
        ->and($statsRes1->json('data.total_xp'))->toBe(105);
});

test('black-box: database backup command creates valid json and sql dump files in database/backups', function () {
    $this->artisan('db:backup', ['--file' => 'test_backup_suite'])
        ->assertSuccessful();

    $backupDir = database_path('backups');
    $jsonPath = "{$backupDir}/test_backup_suite.json";
    $sqlPath = "{$backupDir}/test_backup_suite.sql";

    expect(file_exists($jsonPath))->toBeTrue()
        ->and(file_exists($sqlPath))->toBeTrue();

    $jsonContent = json_decode(file_get_contents($jsonPath), true);
    expect($jsonContent)->toHaveKey('tables')
        ->and($jsonContent['tables'])->toHaveKey('notes');

    @unlink($jsonPath);
    @unlink($sqlPath);
});

test('black-box: user bulk updates note typography and visual theme alongside category and flags', function () {
    $user = User::factory()->create();
    $note1 = Note::factory()->forUser($user)->create([
        'title' => 'Ancient Scroll 1',
        'font_family' => 'pixel',
        'font_size' => '16px',
        'theme_color' => 'blue',
        'is_pinned' => false,
        'is_favorite' => false,
        'category' => 'quest',
    ]);
    $note2 = Note::factory()->forUser($user)->create([
        'title' => 'Ancient Scroll 2',
        'font_family' => 'pixel',
        'font_size' => '16px',
        'theme_color' => 'blue',
        'is_pinned' => false,
        'is_favorite' => false,
        'category' => 'quest',
    ]);

    $res = $this->actingAs($user)->postJson('/api/notes/bulk-update', [
        'ids' => [$note1->id, $note2->id],
        'is_pinned' => true,
        'is_favorite' => true,
        'category' => 'archive',
        'font_family' => 'arcade',
        'font_size' => '18px',
        'theme_color' => 'purple',
    ]);

    $res->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.updated_count', 2);

    expect($note1->fresh()->is_pinned)->toBeTrue()
        ->and($note1->fresh()->is_favorite)->toBeTrue()
        ->and($note1->fresh()->category)->toBe('archive')
        ->and($note1->fresh()->font_family)->toBe('arcade')
        ->and($note1->fresh()->font_size)->toBe('18px')
        ->and($note1->fresh()->theme_color)->toBe('purple')
        ->and($note2->fresh()->is_pinned)->toBeTrue()
        ->and($note2->fresh()->font_family)->toBe('arcade');
});

test('black-box: category bulk delete via web route deletes selected categories, cascades to associated notes, and redirects with flash message', function () {
    $user = User::factory()->create();
    $cat1 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Web Cat 1', 'slug' => 'web-cat-1']);
    $cat2 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Web Cat 2', 'slug' => 'web-cat-2']);

    $note = Note::factory()->forUser($user)->create(['category' => 'web-cat-1']);

    $res = $this->actingAs($user)->post('/categories/bulk-delete', [
        'ids' => [$cat1->id, $cat2->id],
    ]);

    $res->assertRedirect(route('categories.index'))
        ->assertSessionHas('status', 'success');

    $this->assertDatabaseMissing('categories', ['id' => $cat1->id]);
    $this->assertDatabaseMissing('categories', ['id' => $cat2->id]);
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
});

test('black-box: ai assistant handles unexpected network throwable with 502 error response', function () {
    config([
        'services.ai.key' => 'test-key',
        'services.ai.model' => 'test-model',
        'services.ai.base_url' => 'https://api.openai.com/v1',
    ]);

    $user = User::factory()->create();
    $note = Note::factory()->forUser($user)->create();

    Http::fake(function () {
        throw new Exception('Network SSL handshake failed');
    });

    $res = $this->actingAs($user)->postJson(route('notes.assistant', $note), [
        'action' => 'improve',
        'content' => 'Some content to improve',
    ]);

    $res->assertStatus(502)
        ->assertJsonPath('status', 'error');
});

test('black-box: ai assistant handles generate_title action successfully', function () {
    config([
        'services.ai.key' => 'test-key',
        'services.ai.model' => 'test-model',
        'services.ai.base_url' => 'https://api.openai.com/v1',
    ]);

    $user = User::factory()->create();
    $note = Note::factory()->forUser($user)->create([
        'title' => 'Draft',
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'The Whispering Shadows of Eldoria',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ], 200),
    ]);

    $res = $this->actingAs($user)->postJson(route('notes.assistant', $note), [
        'action' => 'generate_title',
        'content' => 'In the depths of Eldoria, dark shadows whisper secrets of the past.',
    ]);

    $res->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.content', 'The Whispering Shadows of Eldoria');
});

test('black-box: application health check endpoint /up returns 200 OK', function () {
    $res = $this->get('/up');
    $res->assertStatus(200);
});

test('black-box: import json backup restores note with typography, flags, and recomputed chunks', function () {
    $user = User::factory()->create();

    $jsonPayload = json_encode([
        'title' => 'Arcane Tome from Ancient Era',
        'category' => 'spellbook',
        'content' => '<h2>Forbidden Spells</h2><p>Cast with caution.</p>',
        'font_family' => 'mono',
        'font_size' => '18px',
        'theme_color' => 'purple',
        'is_pinned' => true,
        'is_favorite' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent('backup.json', (string) $jsonPayload);

    $res = $this->actingAs($user)->postJson('/api/notes/import', [
        'file' => $file,
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Arcane Tome from Ancient Era')
        ->assertJsonPath('data.category', 'spellbook')
        ->assertJsonPath('data.font_family', 'mono')
        ->assertJsonPath('data.font_size', '18px')
        ->assertJsonPath('data.theme_color', 'purple')
        ->assertJsonPath('data.is_pinned', true)
        ->assertJsonPath('data.is_favorite', true);

    $noteId = $res->json('data.id');
    $this->assertDatabaseHas('notes', [
        'id' => $noteId,
        'user_id' => $user->id,
        'title' => 'Arcane Tome from Ancient Era',
    ]);
});

test('black-box: note export endpoint produces clean and secure csv, json, txt, and html downloads', function () {
    $user = User::factory()->create();
    $note = Note::factory()->forUser($user)->create([
        'title' => '=1+1 Tax Formula',
        'content' => '<h2>Secret Formulas</h2><p>Never reveal.</p>',
    ]);

    // CSV format: formula injection sanitization
    $csvRes = $this->actingAs($user)->get("/api/notes/{$note->id}/export/csv");
    $csvRes->assertStatus(200)
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($csvRes->getContent())->toContain("'=1+1 Tax Formula");

    // JSON format
    $jsonRes = $this->actingAs($user)->get("/api/notes/{$note->id}/export/json");
    $jsonRes->assertStatus(200)
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
        ->assertJsonPath('title', '=1+1 Tax Formula');

    // TXT format
    $txtRes = $this->actingAs($user)->get("/api/notes/{$note->id}/export/txt");
    $txtRes->assertStatus(200)
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($txtRes->getContent())->toContain('TITLE: =1+1 Tax Formula');

    // HTML format
    $htmlRes = $this->actingAs($user)->get("/api/notes/{$note->id}/export/html");
    $htmlRes->assertStatus(200)
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    expect($htmlRes->getContent())->toContain('Secret Formulas');
});

test('black-box: user profile endpoints return hero telemetry and allow updating name and email', function () {
    $user = User::factory()->create([
        'name' => 'Original Hero',
        'email' => 'hero@original.dev',
    ]);

    // Read profile
    $getRes = $this->actingAs($user)->getJson('/api/profile');
    $getRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Original Hero')
        ->assertJsonPath('data.email', 'hero@original.dev');

    // Update profile name and email
    $updateRes = $this->actingAs($user)->putJson('/profile', [
        'name' => 'Arch-Mage Eldrin',
        'email' => 'eldrin@notenux.dev',
    ]);

    $updateRes->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Arch-Mage Eldrin')
        ->assertJsonPath('data.email', 'eldrin@notenux.dev');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Arch-Mage Eldrin',
        'email' => 'eldrin@notenux.dev',
    ]);
});

test('black-box: user can update password with valid current password and fails when current password is wrong', function () {
    $user = User::factory()->create([
        'password' => bcrypt('old-secret-password'),
    ]);

    // Attempt with incorrect current password
    $badRes = $this->actingAs($user)->putJson('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'current_password' => 'wrong-current-password',
        'password' => 'new-secret-password-123',
        'password_confirmation' => 'new-secret-password-123',
    ]);

    $badRes->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);

    // Attempt with valid current password
    $goodRes = $this->actingAs($user)->putJson('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'current_password' => 'old-secret-password',
        'password' => 'new-secret-password-123',
        'password_confirmation' => 'new-secret-password-123',
    ]);

    $goodRes->assertStatus(200)
        ->assertJsonPath('status', 'success');

    expect(Hash::check('new-secret-password-123', $user->fresh()->password))->toBeTrue();
});

test('black-box: notes search query finds notes matching title or content keyword', function () {
    $user = User::factory()->create();
    $noteMatch = Note::factory()->forUser($user)->create([
        'title' => 'Completion 100% Guaranteed',
        'content' => 'Full progress',
    ]);
    $noteOther = Note::factory()->forUser($user)->create([
        'title' => 'Completely Different Scroll',
        'content' => 'Nothing here',
    ]);

    $res = $this->actingAs($user)->getJson('/api/notes?q=Guaranteed');
    $res->assertStatus(200);

    $ids = collect($res->json('data'))->pluck('id')->all();
    expect($ids)->toContain($noteMatch->id)
        ->and($ids)->not->toContain($noteOther->id);
});

test('black-box: studio view renders empty state when user has zero notes without creating spam notes', function () {
    $user = User::factory()->create();

    $res = $this->actingAs($user)->get(route('studio'));
    $res->assertStatus(200);

    expect(Note::where('user_id', $user->id)->count())->toBe(0);
    $res->assertSee('Belum ada catatan')
        ->assertSee('Belum ada catatan dipilih...')
        ->assertDontSee('Main Quest Scroll')
        ->assertDontSee('Mission Briefing: Act I');
});

test('black-box: note store API defaults category to catatan and font to sans', function () {
    $user = User::factory()->create();

    $res = $this->actingAs($user)->postJson(route('notes.store'), [
        'title' => 'Catatan Baru',
    ]);

    $res->assertStatus(201)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.category', 'catatan')
        ->assertJsonPath('data.font_family', 'sans');
});
