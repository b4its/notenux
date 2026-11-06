<?php

use App\Models\Note;
use App\Services\NoteChunkService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    Note::factory()->create(['title' => 'B Note', 'category' => 'spellbook', 'word_count' => 100]);
    Note::factory()->create(['title' => 'A Note', 'category' => 'spellbook', 'word_count' => 500]);
    Note::factory()->create(['title' => 'C Note', 'category' => 'quest', 'word_count' => 300]);

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
