<?php

use App\Models\Note;
use App\Models\NoteChunk;
use App\Services\NoteChunkService;

test('it chunks large content into multiple identifiable segments', function () {
    $service = new NoteChunkService;

    // Create a mock long content with paragraphs
    $paragraph = str_repeat('The quick brown fox jumps over the lazy dog. ', 30); // ~1350 chars
    $fullContent = "<p>{$paragraph}</p><p>{$paragraph}</p>";

    $chunks = $service->splitIntoChunks($fullContent);

    expect(count($chunks))->toBeGreaterThan(1);
    foreach ($chunks as $chunk) {
        expect($chunk['chunk_uid'])->toStartWith('chk_')
            ->and($chunk['content'])->not->toBeEmpty();
    }
});

test('it detects semantic block types correctly', function () {
    $service = new NoteChunkService;

    expect($service->detectChunkType('<h2>Quest Objective</h2>'))->toBe('heading')
        ->and($service->detectChunkType('### Markdown Heading'))->toBe('heading')
        ->and($service->detectChunkType('<pre><code>code sample</code></pre>'))->toBe('code')
        ->and($service->detectChunkType('```js console.log() ```'))->toBe('code')
        ->and($service->detectChunkType('<blockquote>Arcane wisdom</blockquote>'))->toBe('quote')
        ->and($service->detectChunkType('<ul><li>item 1</li></ul>'))->toBe('list')
        ->and($service->detectChunkType('<p>Plain narrative story text</p>'))->toBe('paragraph');
});

test('it synchronizes database chunks and updates gamified metrics', function () {
    $service = new NoteChunkService;

    /** @var Note $note */
    $note = Note::factory()->create([
        'title' => 'Arcane Scroll of Wisdom',
        'content' => '',
    ]);

    $content = '<h2>Arcane Wisdom</h2><p>This is a legendary document written by the ancient sorcerer of blue fire.</p>';
    $chunks = $service->syncChunks($note, $content);

    expect($chunks)->not->toBeEmpty()
        ->and(NoteChunk::where('note_id', $note->id)->count())->toBe(count($chunks))
        ->and($note->fresh()->word_count)->toBeGreaterThan(0)
        ->and($note->fresh()->xp)->toBeGreaterThanOrEqual(10)
        ->and($note->fresh()->level)->toBeGreaterThanOrEqual(1);

    // Test reassemble
    $reassembled = $service->reassemble($note);
    expect($reassembled)->toBe($content);
});
