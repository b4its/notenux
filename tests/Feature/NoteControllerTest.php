<?php

use App\Models\Note;
use App\Models\NoteChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('it lists notes via json api', function () {
    Note::factory()->count(3)->create();

    $response = $this->getJson(route('notes.index'));

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'uuid', 'title', 'category', 'font_family', 'font_size', 'is_pinned'],
            ],
        ]);
});

test('it creates a note and generates chunks with unique identities', function () {
    $payload = [
        'title' => 'Legendary Sword Quest',
        'category' => 'quest',
        'content' => '<h2>The Legend Begins</h2><p>Deep within the ancient mountain cave lies the blade of lightning.</p>',
        'font_family' => 'pixel',
        'font_size' => '16px',
        'theme_color' => 'blue',
    ];

    $response = $this->postJson(route('notes.store'), $payload);

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Legendary Sword Quest');

    $noteId = $response->json('data.id');
    $this->assertDatabaseHas('notes', [
        'id' => $noteId,
        'title' => 'Legendary Sword Quest',
    ]);

    $chunks = NoteChunk::where('note_id', $noteId)->get();
    expect($chunks->count())->toBeGreaterThan(0);

    foreach ($chunks as $chunk) {
        expect($chunk->chunk_uid)->toStartWith('chk_')
            ->and($chunk->checksum)->not->toBeEmpty();
    }
});

test('it shows a note with its chunks', function () {
    $note = Note::factory()->create();
    NoteChunk::factory()->count(2)->create(['note_id' => $note->id]);

    $response = $this->getJson(route('notes.show', $note));

    $response->assertOk()
        ->assertJsonPath('data.id', $note->id)
        ->assertJsonCount(2, 'data.chunks');
});

test('it updates a note and re-chunks content', function () {
    $note = Note::factory()->create(['title' => 'Old Title']);
    NoteChunk::factory()->create(['note_id' => $note->id]);

    $response = $this->putJson(route('notes.update', $note), [
        'title' => 'Updated Epic Title',
        'content' => '<h3>Phase 1</h3><p>New content section for testing.</p>',
        'font_family' => 'mono',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Updated Epic Title')
        ->assertJsonPath('data.font_family', 'mono');

    $this->assertDatabaseHas('notes', [
        'id' => $note->id,
        'title' => 'Updated Epic Title',
        'font_family' => 'mono',
    ]);
});

test('it toggles pin status on note', function () {
    $note = Note::factory()->create(['is_pinned' => false]);

    $response = $this->postJson(route('notes.pin', $note));
    $response->assertOk()->assertJsonPath('is_pinned', true);

    $response2 = $this->postJson(route('notes.pin', $note));
    $response2->assertOk()->assertJsonPath('is_pinned', false);
});

test('it inspects database chunks for identity verification', function () {
    $note = Note::factory()->create();
    $uid = 'chk_test_'.(string) Str::ulid();
    $chunk = NoteChunk::factory()->create([
        'note_id' => $note->id,
        'chunk_uid' => $uid,
    ]);

    $response = $this->getJson(route('notes.chunks', $note));

    $response->assertOk()
        ->assertJsonPath('note_id', $note->id)
        ->assertJsonPath('total_chunks', 1)
        ->assertJsonPath('chunks.0.chunk_uid', $uid);
});

test('it deletes a note and cascades its chunks', function () {
    $note = Note::factory()->create();
    $chunk = NoteChunk::factory()->create(['note_id' => $note->id]);

    $response = $this->deleteJson(route('notes.destroy', $note));

    $response->assertOk();
    $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    $this->assertDatabaseMissing('note_chunks', ['id' => $chunk->id]);
});

test('it returns player stats and gamified rank', function () {
    Note::factory()->create(['word_count' => 300, 'xp' => 450]);

    $response = $this->getJson(route('api.stats'));

    $response->assertOk()
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
            ],
        ]);
});
