<?php

use App\Http\Controllers\NoteController;
use App\Models\Note;
use App\Models\NoteChunk;
use App\Models\User;
use App\Services\NoteChunkService;
use App\Services\NoteContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| WHITE BOX TESTING: Internal Logic, Edge Cases, Data Integrity & Methods
|--------------------------------------------------------------------------
*/

test('white-box: chunk service handles empty or whitespace-only content', function () {
    $service = new NoteChunkService;
    $chunks = $service->splitIntoChunks('   ');

    expect($chunks)->toHaveCount(1)
        ->and($chunks[0]['chunk_uid'])->toStartWith('chk_')
        ->and($chunks[0]['chunk_type'])->toBe('paragraph')
        ->and($chunks[0]['content'])->toBe('');
});

test('white-box: chunk service detects all semantic block types including tables and tasks', function () {
    $service = new NoteChunkService;

    expect($service->detectChunkType('# Heading 1'))->toBe('heading')
        ->and($service->detectChunkType('<h2>Subheading</h2>'))->toBe('heading')
        ->and($service->detectChunkType('```python\nprint(1)\n```'))->toBe('code')
        ->and($service->detectChunkType('<pre><code>code</code></pre>'))->toBe('code')
        ->and($service->detectChunkType('> Wisdom from the void'))->toBe('quote')
        ->and($service->detectChunkType('<blockquote>Arcane</blockquote>'))->toBe('quote')
        ->and($service->detectChunkType('| Header 1 | Header 2 |'))->toBe('table')
        ->and($service->detectChunkType('<table class="retro-table"><tr><td>Data</td></tr></table>'))->toBe('table')
        ->and($service->detectChunkType('- [ ] Defeat the dragon'))->toBe('task')
        ->and($service->detectChunkType('* [x] Brew health potion'))->toBe('task')
        ->and($service->detectChunkType('<ul class="task-list"><li><input type="checkbox"> Test</li></ul>'))->toBe('task')
        ->and($service->detectChunkType('- Normal item'))->toBe('list')
        ->and($service->detectChunkType('1. Numbered item'))->toBe('list')
        ->and($service->detectChunkType('<p>Plain narrative story text</p>'))->toBe('paragraph');
});

test('white-box: chunk service maintains exact checksum and byte size integrity', function () {
    $service = new NoteChunkService;
    $note = Note::factory()->create();

    $content = '<h2>Arcane Checksum Test</h2><p>Data integrity is paramount for memory matrices.</p>';
    $chunks = $service->syncChunks($note, $content);

    foreach ($chunks as $chunk) {
        expect($chunk->byte_size)->toBe(strlen($chunk->content))
            ->and($chunk->checksum)->toBe(hash('sha256', $chunk->content));
    }

    $reassembled = $service->reassemble($note);
    expect($reassembled)->toBe($content);
});

test('white-box: note model booted method auto-generates uuid if empty', function () {
    $note = Note::create([
        'title' => 'Test UUID Generation',
        'content' => 'Sample content',
    ]);

    expect($note->uuid)->not->toBeEmpty()
        ->and(Str::isUuid($note->uuid))->toBeTrue();
});

test('white-box: note reading time accessor handles varying word counts', function () {
    $noteShort = Note::factory()->create(['word_count' => 50]);
    $noteLong = Note::factory()->create(['word_count' => 600]);

    expect($noteShort->reading_time)->toBe('1 min read')
        ->and($noteLong->reading_time)->toBe('4 min read');
});

test('white-box: controller rejects invalid font family', function () {
    $response = $this->postJson(route('notes.store'), [
        'title' => 'Invalid Font Test',
        'font_family' => 'comic-sans-unsupported',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['font_family']);
});

test('white-box: controller rejects title exceeding max length', function () {
    $response = $this->postJson(route('notes.store'), [
        'title' => str_repeat('A', 300),
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

test('white-box: controller duplicate method clones note with fresh uuid and independent chunks', function () {
    $original = Note::factory()->create(['title' => 'Spell of Fire', 'content' => '<p>Burn brightly</p>']);
    $service = app(NoteChunkService::class);
    $service->syncChunks($original, $original->content);

    $response = $this->postJson(route('notes.duplicate', $original));

    $response->assertCreated()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.title', 'Spell of Fire (Copy)');

    $cloneId = $response->json('data.id');
    $clone = Note::find($cloneId);

    expect($clone)->not->toBeNull()
        ->and($clone->id)->not->toBe($original->id)
        ->and($clone->uuid)->not->toBe($original->uuid)
        ->and($clone->chunks()->count())->toBe($original->chunks()->count());
});

test('white-box: controller toggleFavorite flips boolean status', function () {
    $note = Note::factory()->create(['is_favorite' => false]);

    $res1 = $this->postJson(route('notes.favorite', $note));
    $res1->assertOk()->assertJsonPath('is_favorite', true);
    expect($note->fresh()->is_favorite)->toBeTrue();

    $res2 = $this->postJson(route('notes.favorite', $note));
    $res2->assertOk()->assertJsonPath('is_favorite', false);
    expect($note->fresh()->is_favorite)->toBeFalse();
});

test('white-box: controller exports markdown, txt, and html with correct headers', function () {
    $note = Note::factory()->create([
        'title' => 'Arcane Export Scroll',
        'category' => 'spellbook',
        'content' => '<h2>Spell</h2><p>Cast light.</p>',
    ]);

    // Markdown
    $resMd = $this->get(route('notes.export', ['note' => $note, 'format' => 'md']));
    $resMd->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('# Arcane Export Scroll');

    // Plain text
    $resTxt = $this->get(route('notes.export', ['note' => $note, 'format' => 'txt']));
    $resTxt->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('TITLE: Arcane Export Scroll');

    // HTML
    $resHtml = $this->get(route('notes.export', ['note' => $note, 'format' => 'html']));
    $resHtml->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('<!DOCTYPE html>', false)
        ->assertSee('<h1>Arcane Export Scroll</h1>', false);
});

test('white-box: chunk service verifyIntegrity correctly verifies untampered note shards', function () {
    $service = new NoteChunkService;
    $note = Note::factory()->create([
        'content' => '<h2>Integrity Test</h2><p>All shards are cryptographically hashed using SHA-256.</p>',
    ]);
    $service->syncChunks($note, $note->content);

    $report = $service->verifyIntegrity($note);

    expect($report['status'])->toBe('verified')
        ->and($report['verified'])->toBeTrue()
        ->and($report['total_chunks'])->toBeGreaterThan(0)
        ->and($report['corrupted_chunks'])->toBe(0)
        ->and($report['valid_chunks'])->toBe($report['total_chunks']);
});

test('white-box: chunk service verifyIntegrity flags corrupted or altered shards', function () {
    $service = new NoteChunkService;
    $note = Note::factory()->create([
        'content' => '<h2>Untampered</h2><p>Legitimate text.</p>',
    ]);
    $chunks = $service->syncChunks($note, $note->content);

    // Intentionally tamper with a chunk directly in DB without recalculating checksum
    $firstChunk = $note->chunks()->first();
    $firstChunk->update(['content' => 'Tampered malicious injection']);

    $report = $service->verifyIntegrity($note);

    expect($report['status'])->toBe('corrupted')
        ->and($report['verified'])->toBeFalse()
        ->and($report['corrupted_chunks'])->toBe(1)
        ->and($report['valid_chunks'])->toBe($note->chunks()->count() - 1);
});

test('white-box: chunk service updateChunk updates shard, recalculates checksum and synchronizes parent note', function () {
    $service = new NoteChunkService;
    $note = Note::factory()->create([
        'content' => '<h2>Original Heading</h2><p>Original body text.</p>',
    ]);
    $service->syncChunks($note, $note->content);

    $chunkToEdit = $note->chunks()->first();
    $newContent = '<h2>Updated Arcane Header</h2>';

    $updatedChunk = $service->updateChunk($chunkToEdit, $newContent);

    expect($updatedChunk->content)->toBe($newContent)
        ->and($updatedChunk->checksum)->toBe(hash('sha256', $newContent))
        ->and($updatedChunk->byte_size)->toBe(strlen($newContent));

    // Verify parent note was reassembled
    $refreshedNote = $note->fresh();
    expect($refreshedNote->content)->toContain('Updated Arcane Header');
});

test('white-box: chunk service importMarkdown correctly extracts title and converts tables to chunks', function () {
    $service = new NoteChunkService;
    $markdown = "# Ancient Grimoire of Necromancy\n\nChapter 1: The Gathering.\n\n| Item | Power |\n|---|---|\n| Bone Staff | 85 |";

    $note = $service->importMarkdown($markdown, 'fallback.md', 'spellbook');

    expect($note->title)->toBe('Ancient Grimoire of Necromancy')
        ->and($note->category)->toBe('spellbook')
        ->and($note->content)->toContain('Ancient Grimoire of Necromancy')
        ->and($note->content)->toContain('retro-table')
        ->and($note->chunks()->count())->toBeGreaterThanOrEqual(1);
});

test('white-box: chunk service deleteChunk re-indexes remaining chunks sequentially', function () {
    $service = new NoteChunkService;
    $para = str_repeat('Lore of the ancient realm. ', 50); // ~1350 chars each
    $note = Note::factory()->create([
        'content' => "<h2>Part 1</h2><p>{$para}</p><h2>Part 2</h2><p>{$para}</p><h2>Part 3</h2><p>{$para}</p>",
    ]);
    $service->syncChunks($note, $note->content);

    $initialCount = $note->chunks()->count();
    expect($initialCount)->toBeGreaterThan(1);

    $middleChunk = $note->chunks()->skip(1)->first();
    expect($middleChunk)->not->toBeNull();

    $service->deleteChunk($middleChunk);

    $remaining = $note->chunks()->orderBy('chunk_index', 'asc')->get();
    expect($remaining->count())->toBe($initialCount - 1);

    // Verify chunk_index sequential order 0, 1, 2...
    foreach ($remaining as $idx => $chunk) {
        expect($chunk->chunk_index)->toBe($idx);
    }
});

test('white-box: chunk service reorderChunks updates indexes and reassembles parent note text', function () {
    $service = new NoteChunkService;
    $para = str_repeat('Wisdom of the elders. ', 50);
    $note = Note::factory()->create([
        'content' => "<h2>Alpha Header</h2><p>{$para}</p><h2>Beta Header</h2><p>{$para}</p>",
    ]);
    $service->syncChunks($note, $note->content);

    $chunks = $note->chunks()->orderBy('chunk_index', 'asc')->get();
    expect($chunks->count())->toBeGreaterThan(1);

    // Reverse the order
    $reversedIds = $chunks->pluck('id')->reverse()->values()->all();
    $reordered = $service->reorderChunks($note, $reversedIds);

    expect($reordered->first()->id)->toBe($chunks->last()->id)
        ->and($reordered->last()->id)->toBe($chunks->first()->id);

    $refreshed = $note->fresh();
    expect($refreshed->content)->toBe($service->reassemble($refreshed));
});

test('white-box: index query optimizes performance by omitting heavy content column on list view', function () {
    $note = Note::factory()->create([
        'title' => 'Heavy Lore Scroll',
        'content' => str_repeat('<p>Massive ancient data payload.</p>', 40),
    ]);
    app(NoteChunkService::class)->syncChunks($note, $note->content);

    $response = $this->getJson(route('notes.index'));
    $response->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();

    $first = $data[0];
    expect(array_key_exists('content', $first))->toBeFalse()
        ->and(array_key_exists('title', $first))->toBeTrue()
        ->and(array_key_exists('chunks_count', $first))->toBeTrue();
});

test('white-box: backend automatically chunks text and assigns unique chunk_uid on store', function () {
    $response = $this->postJson(route('notes.store'), [
        'title' => 'Auto Chunked Note',
        'content' => '<h2>Chapter 1</h2><p>Auto-partitioned text segment in database.</p>',
        'category' => 'quest',
    ]);

    $response->assertStatus(201);
    $noteId = $response->json('data.id');

    $chunks = NoteChunk::where('note_id', $noteId)->get();
    expect($chunks->count())->toBeGreaterThan(0);

    foreach ($chunks as $chunk) {
        expect($chunk->chunk_uid)->toStartWith('chk_')
            ->and($chunk->byte_size)->toBeGreaterThan(0)
            ->and($chunk->checksum)->not->toBeNull();
    }
});

test('white-box: chunk service reorders chunks using chunk_uid strings', function () {
    $service = new NoteChunkService;
    $para = str_repeat('Matrix partition shard data. ', 40);
    $note = Note::factory()->create([
        'content' => "<h2>Shard One</h2><p>{$para}</p><h2>Shard Two</h2><p>{$para}</p>",
    ]);
    $service->syncChunks($note, $note->content);

    $chunks = $note->chunks()->orderBy('chunk_index', 'asc')->get();
    expect($chunks->count())->toBeGreaterThan(1);

    // Pass chunk_uid strings instead of integer IDs
    $reversedUids = $chunks->pluck('chunk_uid')->reverse()->values()->all();
    $reordered = $service->reorderChunks($note, $reversedUids);

    expect($reordered->first()->chunk_uid)->toBe($reversedUids[0])
        ->and($reordered->last()->chunk_uid)->toBe($reversedUids[count($reversedUids) - 1]);
});

test('white-box: content sanitizer eliminates malicious XSS vectors while preserving allowed rich markup', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $dirtyHtml = '<h2>Title</h2><script>alert("hack")</script><p onclick="steal()">Safe text <img src="https://example.com/art.png" onerror="alert(1)"></p><iframe src="evil.com"></iframe><table class="retro-table"><tr><td>Data</td></tr></table>';
    $cleanHtml = $sanitizer->sanitize($dirtyHtml);

    expect($cleanHtml)->not->toContain('<script>')
        ->and($cleanHtml)->not->toContain('alert(')
        ->and($cleanHtml)->not->toContain('onclick')
        ->and($cleanHtml)->not->toContain('onerror')
        ->and($cleanHtml)->not->toContain('<iframe>')
        ->and($cleanHtml)->toContain('<h2>Title</h2>')
        ->and($cleanHtml)->toContain('Safe text')
        ->and($cleanHtml)->toContain('<table class="retro-table">')
        ->and($cleanHtml)->toContain('https://example.com/art.png');
});

test('white-box: category controller generates random slug on empty or non-ascii string', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.categories.store'), [
        'name' => '??? !!! ###',
        'color' => 'blue',
    ]);

    $response->assertCreated();
    $slug = $response->json('data.slug');
    expect($slug)->toStartWith('cat_');
});

test('white-box: note reading time accessor handles boundary values and large manuscripts', function () {
    $emptyNote = new Note(['word_count' => 0]);
    expect($emptyNote->reading_time)->toBe('1 min read');

    $smallNote = new Note(['word_count' => 150]);
    expect($smallNote->reading_time)->toBe('1 min read');

    $mediumNote = new Note(['word_count' => 450]);
    expect($mediumNote->reading_time)->toBe('3 min read');

    $hugeNote = new Note(['word_count' => 2000]);
    expect($hugeNote->reading_time)->toBe('12 min read');
});

test('white-box: chunk service reassembles empty and single chunk notes seamlessly', function () {
    $service = new NoteChunkService;

    $emptyNote = Note::factory()->create(['content' => '']);
    $service->syncChunks($emptyNote, '');
    expect($service->reassemble($emptyNote))->toBe('');

    $singleNote = Note::factory()->create(['content' => '<p>Lone shard text.</p>']);
    $service->syncChunks($singleNote, $singleNote->content);
    expect($singleNote->chunks()->count())->toBe(1)
        ->and($service->reassemble($singleNote))->toBe('<p>Lone shard text.</p>');
});

test('white-box: note model appends reading_time to array and json serialization', function () {
    $note = Note::factory()->create(['word_count' => 360]);

    $array = $note->toArray();
    expect($array)->toHaveKey('reading_time')
        ->and($array['reading_time'])->toBe('2 min read');

    $json = json_decode((string) json_encode($note), true);
    expect($json)->toHaveKey('reading_time')
        ->and($json['reading_time'])->toBe('2 min read');
});

test('white-box: note controller convertToMarkdown handles comprehensive html structures', function () {
    $controller = app(NoteController::class);

    $html = '<h3>Subtitle</h3><table class="retro-table"><tr><th>Stat</th><th>Value</th></tr><tr><td>STR</td><td>18</td></tr></table>'
        .'<ul class="task-list"><li><input type="checkbox" checked> Done task</li><li><input type="checkbox"> Open task</li></ul>'
        .'<pre><code>$x = 42;</code></pre>'
        .'<p>Inline <code>code</code> and <a href="https://example.com">Link</a> and <hr></p>';

    $md = $controller->convertToMarkdown($html);

    expect($md)->toContain('### Subtitle')
        ->and($md)->toContain('| Stat | Value |')
        ->and($md)->toContain('| STR | 18 |')
        ->and($md)->toContain('- [x] Done task')
        ->and($md)->toContain('- [ ] Open task')
        ->and($md)->toContain("```\n\$x = 42;\n```")
        ->and($md)->toContain('`code`')
        ->and($md)->toContain('[Link](https://example.com)')
        ->and($md)->toContain('---');
});

test('white-box: note chunk service importMarkdown safely handles json backup with malicious scripts', function () {
    $service = new NoteChunkService;

    $jsonBackup = json_encode([
        'title' => 'Vault Secrets',
        'category' => 'spellbook',
        'content' => '<h2>Secret Formulas</h2><p>Safe content</p><script>alert("hacked")</script><iframe src="malicious.html"></iframe>',
    ]);

    $note = $service->importMarkdown($jsonBackup);

    expect($note->title)->toBe('Vault Secrets')
        ->and($note->category)->toBe('spellbook')
        ->and($note->content)->not->toContain('<script>')
        ->and($note->content)->not->toContain('<iframe>')
        ->and($note->content)->toContain('Secret Formulas')
        ->and($note->chunks()->count())->toBeGreaterThan(0);
});

test('white-box: player stats calculation returns complete progress metrics across endpoints', function () {
    Note::factory()->create(['word_count' => 150, 'xp' => 300]);

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
                'progress_percent',
                'progress_xp',
                'needed_xp',
                'next_level_xp',
                'rank_title',
                'rank_badge',
                'mana',
            ],
        ]);

    $data = $response->json('data');
    expect($data['needed_xp'])->toBe(150)
        ->and($data['next_level_xp'])->toBeGreaterThan($data['total_xp']);
});
