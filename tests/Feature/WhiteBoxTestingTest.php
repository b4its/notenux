<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NoteController;
use App\Models\Category;
use App\Models\Note;
use App\Models\NoteChunk;
use App\Models\User;
use App\Services\NoteChunkService;
use App\Services\NoteContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

test('white-box: content sanitizer preserves safe raster base64 images while stripping malicious svg vectors', function () {
    $sanitizer = new NoteContentSanitizer;
    $safePng = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $unsafeSvg = 'data:image/svg+xml;base64,PHN2ZyBvbmxvYWQ9YWxlcnQoMSk+PC9zdmc+';

    $clean = $sanitizer->sanitize("<p><img src=\"{$safePng}\"><img src=\"{$unsafeSvg}\"></p>");

    expect($clean)->toContain($safePng)
        ->and($clean)->not->toContain($unsafeSvg);
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

test('white-box: user can save note with pasted base64 image and chunk content is stored without truncation', function () {
    $note = Note::factory()->create(['title' => 'Scroll with Screenshot']);
    $pngBase64 = 'data:image/png;base64,'.base64_encode(str_repeat('X', 70000));
    $content = "<p>Intro text</p><p><img src=\"{$pngBase64}\" alt=\"Pasted Screenshot\"></p><p>Outro text</p>";

    $response = $this->putJson(route('notes.update', $note), [
        'content' => $content,
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    $freshNote = $note->fresh();
    expect($freshNote->content)->toContain($pngBase64)
        ->and($freshNote->chunks()->count())->toBeGreaterThan(0);

    $imageChunk = $freshNote->chunks()->where('content', 'like', '%data:image/png;base64%')->first();
    expect($imageChunk)->not->toBeNull()
        ->and($imageChunk->byte_size)->toBeGreaterThan(70000)
        ->and($imageChunk->content)->toContain($pngBase64);
});

test('white-box: note controller convertToMarkdown correctly formats embedded base64 images', function () {
    $controller = app(NoteController::class);
    $pngBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    $html = "<p>Diagram</p><img src=\"{$pngBase64}\" alt=\"Architecture Diagram\"><p>Details</p>";

    $markdown = $controller->convertToMarkdown($html);

    expect($markdown)->toContain("![Architecture Diagram]({$pngBase64})");
});

test('white-box: note controller convertToMarkdown preserves images embedded inside table cells', function () {
    $controller = app(NoteController::class);
    $imgUrl = 'https://notenux.dev/assets/logo/icon_dark.png';
    $html = '<table class="retro-table"><thead><tr><th>Item</th><th>Preview</th></tr></thead><tbody><tr><td>Shield</td><td><img src="'.$imgUrl.'" alt="Dark Emblem"></td></tr></tbody></table>';

    $markdown = $controller->convertToMarkdown($html);

    expect($markdown)->toContain('| Item | Preview |');
    expect($markdown)->toContain("![Dark Emblem]({$imgUrl})");
});

test('white-box: content sanitizer preserves custom table styling and cell attributes', function () {
    $sanitizer = app(NoteContentSanitizer::class);
    $html = '<table style="border: 2px solid #000; width: 100%;"><tr bgcolor="#1e3a8a"><th style="color: #ffffff; text-align: center;">Head</th></tr><tr><td style="background-color: #06b6d4; text-align: right;" align="right" colspan="1">Val</td></tr></table>';

    $clean = $sanitizer->sanitize($html);

    expect($clean)->toContain('background-color: #06b6d4');
    expect($clean)->toContain('text-align: right');
    expect($clean)->toContain('color: #ffffff');
    expect($clean)->toContain('colspan="1"');
});

test('white-box: note deleting model event cleanly purges all associated chunks from database', function () {
    $note = Note::factory()->create();
    $service = app(NoteChunkService::class);
    $service->syncChunks($note, '<p>Section 1</p><p>Section 2</p>');

    expect($note->chunks()->count())->toBeGreaterThan(0);
    $chunkIds = $note->chunks()->pluck('id')->all();

    $note->delete();

    expect(NoteChunk::whereIn('id', $chunkIds)->exists())->toBeFalse();
});

test('white-box: note chunk service splits deeply nested html table with cell images without breaking tags', function () {
    $service = app(NoteChunkService::class);
    $html = '<table class="retro-table"><thead><tr><th>Hero</th><th>Item</th></tr></thead><tbody><tr><td>Paladin</td><td><img src="https://notenux.dev/sword.png" alt="Sword"></td></tr><tr><td>Wizard</td><td><img src="https://notenux.dev/staff.png" alt="Staff"></td></tr></tbody></table>';

    $chunks = $service->splitIntoChunks($html);
    expect($chunks)->not->toBeEmpty();

    foreach ($chunks as $chunk) {
        // Ensure no broken HTML tag boundaries
        $openCount = substr_count($chunk['content'], '<table');
        $closeCount = substr_count($chunk['content'], '</table>');
        expect($openCount)->toBe($closeCount);
    }
});

test('white-box: content sanitizer removes malicious event handlers and javascript pseudo-protocols from links and images', function () {
    $sanitizer = app(NoteContentSanitizer::class);
    $dirty = '<p><a href="javascript:alert(1)" onclick="steal()" onmouseover="hack()">Link</a><img src="javascript:evil()" onerror="pwn()" alt="Evil"><img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==" alt="XSS"></p>';

    $clean = $sanitizer->sanitize($dirty);

    expect($clean)->not->toContain('javascript:')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('onmouseover')
        ->and($clean)->not->toContain('onerror')
        ->and($clean)->not->toContain('steal')
        ->and($clean)->not->toContain('data:text/html');
});

test('white-box: category slug allocation skips reserved slug all and resolves multiple collisions sequentially', function () {
    $controller = app(CategoryController::class);
    $allocate = new ReflectionMethod($controller, 'allocateSlug');
    $allocate->setAccessible(true);

    // Collision with 'all'
    $slug1 = $allocate->invoke($controller, 'all');
    expect($slug1)->not->toBe('all')
        ->and($slug1)->toBe('all-2');

    // Create a category to collide with
    Category::factory()->create(['slug' => 'spells']);
    Category::factory()->create(['slug' => 'spells-2']);

    $slug2 = $allocate->invoke($controller, 'spells');
    expect($slug2)->toBe('spells-3');
});

test('white-box: note controller export defaults to markdown for unknown or unrecognized formats safely', function () {
    $note = Note::factory()->create(['title' => 'Ancient Scroll', 'content' => '<p>Lore of the ancients</p>']);
    $controller = app(NoteController::class);

    $response = $controller->export($note, 'unrecognized_format');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/markdown')
        ->and($response->getContent())->toContain('# Ancient Scroll')
        ->and($response->getContent())->toContain('Lore of the ancients');
});

test('white-box: note controller export formats plain text with clean stripped tags and telemetry headers', function () {
    $note = Note::factory()->create([
        'title' => 'Arcane Chronicles',
        'category' => 'spellbook',
        'content' => '<h1>Chapter 1</h1><p>Incantation words here.</p><table class="retro-table"><tr><td>Col1</td></tr></table>',
        'word_count' => 5,
        'xp' => 15,
    ]);
    $controller = app(NoteController::class);

    $response = $controller->export($note, 'txt');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->getContent())->toContain('TITLE: Arcane Chronicles')
        ->and($response->getContent())->toContain('CATEGORY: spellbook')
        ->and($response->getContent())->toContain('Incantation words here.')
        ->and($response->getContent())->not->toContain('<h1>')
        ->and($response->getContent())->not->toContain('<table');
});

test('white-box: note controller convertToPlainText converts tasks, ordered lists, and tables without formatting artifacts', function () {
    $controller = app(NoteController::class);

    $html = <<<'HTML'
<h2>Daily Quests</h2>
<ul>
    <li><input type="checkbox"> Slay the Dungeon Boss</li>
    <li><input type="checkbox" checked> Gather 10 Mystic Herbs</li>
</ul>
<ol>
    <li>Enter Cave</li>
    <li>Find Torch</li>
</ol>
<table>
    <tr><th>Skill</th><th>Level</th></tr>
    <tr><td>Archery</td><td>99</td></tr>
</table>
HTML;

    $plain = $controller->convertToPlainText($html);

    expect($plain)->toContain('Daily Quests')
        ->and($plain)->toContain('[ ] Slay the Dungeon Boss')
        ->and($plain)->toContain('[x] Gather 10 Mystic Herbs')
        ->and($plain)->toContain("1. Enter Cave\n2. Find Torch")
        ->and($plain)->toContain("Skill\tLevel\nArchery\t99")
        ->and($plain)->not->toContain('<li')
        ->and($plain)->not->toContain('<input')
        ->and($plain)->not->toContain('<table');
});

test('white-box: note controller export json contains complete chunk array with chunk_uid, checksum, and byte_size', function () {
    $note = Note::factory()->create([
        'title' => 'Telemetry Scroll',
        'content' => '<p>Chunk one content</p><p>Chunk two content</p>',
    ]);
    $service = app(NoteChunkService::class);
    $service->syncChunks($note, $note->content);

    $controller = app(NoteController::class);
    $response = $controller->export($note, 'json');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('application/json');

    $decoded = json_decode($response->getContent(), true);
    expect($decoded['id'])->toBe($note->id)
        ->and($decoded['title'])->toBe('Telemetry Scroll')
        ->and($decoded['chunks'])->not->toBeEmpty()
        ->and($decoded['chunks'][0])->toHaveKeys(['id', 'chunk_uid', 'chunk_index', 'chunk_type', 'content', 'byte_size', 'checksum']);
});

test('white-box: note controller bulkDestroy validates required ids array and rejects invalid elements', function () {
    $controller = app(NoteController::class);

    expect(fn () => $controller->bulkDestroy(Request::create('/api/notes/bulk-delete', 'POST', ['ids' => []])))
        ->toThrow(ValidationException::class);

    expect(fn () => $controller->bulkDestroy(Request::create('/api/notes/bulk-delete', 'POST', ['ids' => [999999]])))
        ->toThrow(ValidationException::class);
});

test('white-box: note controller bulkDestroy atomically deletes multiple notes and cascading chunks', function () {
    $note1 = Note::factory()->create(['title' => 'Scroll Alpha', 'content' => '<p>Alpha content</p>']);
    $note2 = Note::factory()->create(['title' => 'Scroll Beta', 'content' => '<p>Beta content</p>']);
    $service = app(NoteChunkService::class);
    $service->syncChunks($note1, $note1->content);
    $service->syncChunks($note2, $note2->content);

    $chunkIds = NoteChunk::whereIn('note_id', [$note1->id, $note2->id])->pluck('id')->all();
    expect($chunkIds)->not->toBeEmpty();

    $controller = app(NoteController::class);
    $response = $controller->bulkDestroy(Request::create('/api/notes/bulk-delete', 'POST', [
        'ids' => [$note1->id, $note2->id],
    ]));

    expect($response->getStatusCode())->toBe(200);
    $data = $response->getData(true);
    expect($data['status'])->toBe('success')
        ->and($data['data']['deleted_count'])->toBe(2)
        ->and($data['data']['deleted_ids'])->toContain($note1->id, $note2->id)
        ->and(Note::whereIn('id', [$note1->id, $note2->id])->exists())->toBeFalse()
        ->and(NoteChunk::whereIn('id', $chunkIds)->exists())->toBeFalse();
});

test('white-box: category controller bulkDestroy prevents unauthorized user from banishing other users categories', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $catUser1 = Category::factory()->create(['user_id' => $user1->id, 'name' => 'User 1 Secrets']);

    $request = Request::create('/categories/bulk-delete', 'POST', [
        'ids' => [$catUser1->id],
    ]);
    $request->setUserResolver(fn () => $user2);

    $controller = app(CategoryController::class);

    expect(fn () => $controller->bulkDestroy($request))->toThrow(HttpException::class);
});

test('white-box: category controller bulkDestroy cascades deletion to categories, notes, and chunks atomically', function () {
    $user = User::factory()->create();
    $cat1 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Bulk Realm 1', 'slug' => 'bulk-realm-1']);
    $cat2 = Category::factory()->create(['user_id' => $user->id, 'name' => 'Bulk Realm 2', 'slug' => 'bulk-realm-2']);

    $note1 = Note::factory()->create(['category' => 'bulk-realm-1', 'title' => 'Note 1']);
    $note2 = Note::factory()->create(['category' => 'bulk-realm-2', 'title' => 'Note 2']);

    $service = app(NoteChunkService::class);
    $service->syncChunks($note1, '<p>Content 1</p>');
    $service->syncChunks($note2, '<p>Content 2</p>');

    $request = Request::create('/api/categories/bulk-delete', 'POST', [
        'ids' => [$cat1->id, $cat2->id],
    ]);
    $request->headers->set('Accept', 'application/json');
    $request->setUserResolver(fn () => $user);

    $controller = app(CategoryController::class);
    $response = $controller->bulkDestroy($request);

    expect($response->getStatusCode())->toBe(200);
    $data = $response->getData(true);
    expect($data['status'])->toBe('success')
        ->and($data['data']['deleted_count'])->toBe(2)
        ->and(Category::whereIn('id', [$cat1->id, $cat2->id])->exists())->toBeFalse()
        ->and(Note::whereIn('id', [$note1->id, $note2->id])->exists())->toBeFalse()
        ->and(NoteChunk::whereIn('note_id', [$note1->id, $note2->id])->exists())->toBeFalse();
});

test('white-box: note controller store accepts and persists initial is_pinned and is_favorite flags', function () {
    $controller = app(NoteController::class);

    $request = Request::create('/api/notes', 'POST', [
        'title' => 'Legendary Star Scroll',
        'category' => 'spellbook',
        'content' => '<p>Ancient incantation</p>',
        'is_pinned' => true,
        'is_favorite' => true,
    ]);

    $response = $controller->store($request);
    expect($response->getStatusCode())->toBe(201);

    $data = $response->getData(true);
    expect($data['status'])->toBe('success')
        ->and($data['data']['is_pinned'])->toBeTrue()
        ->and($data['data']['is_favorite'])->toBeTrue();
});

test('white-box: chunk service detectChunkType detects html checklist items with checkbox inputs as task', function () {
    $service = app(NoteChunkService::class);

    expect($service->detectChunkType('<li><input type="checkbox"> Slay dungeon beast</li>'))->toBe('task')
        ->and($service->detectChunkType('<p><input type="checkbox" checked> Gather red herbs</p>'))->toBe('task')
        ->and($service->detectChunkType('<ul class="task-list"><li>Item</li></ul>'))->toBe('task')
        ->and($service->detectChunkType('- [ ] Simple markdown task'))->toBe('task')
        ->and($service->detectChunkType('<p>Regular paragraph text</p>'))->toBe('paragraph');
});

test('white-box: ai assistant controller returns 503 when ai credentials are not configured', function () {
    config(['services.ai.key' => null, 'services.ai.model' => null]);
    $note = Note::factory()->create(['title' => 'Quest Journal']);

    $controller = app(AiAssistantController::class);
    $request = Request::create("/api/notes/{$note->id}/assistant", 'POST', [
        'action' => 'improve',
        'content' => 'Improve this text please.',
    ]);

    $response = $controller->store($request, $note);
    expect($response->getStatusCode())->toBe(503);

    $data = $response->getData(true);
    expect($data['status'])->toBe('error')
        ->and($data['message'])->toContain('AI assistant belum dikonfigurasi');
});

test('white-box: note controller index filters by virtual pinned and favorites category params', function () {
    $controller = app(NoteController::class);

    $pinnedNote = Note::factory()->create([
        'title' => 'Pinned Quest',
        'category' => 'quest',
        'is_pinned' => true,
        'is_favorite' => false,
    ]);

    $favNote = Note::factory()->create([
        'title' => 'Favorite Lore',
        'category' => 'lore',
        'is_pinned' => false,
        'is_favorite' => true,
    ]);

    $regularNote = Note::factory()->create([
        'title' => 'Regular Journal',
        'category' => 'journal',
        'is_pinned' => false,
        'is_favorite' => false,
    ]);

    // Request with category=pinned
    $reqPinned = Request::create('/api/notes', 'GET', ['category' => 'pinned']);
    $reqPinned->headers->set('Accept', 'application/json');
    $resPinned = $controller->index($reqPinned);
    $pinnedData = $resPinned->getData(true)['data'];
    $pinnedIds = array_column($pinnedData, 'id');
    expect($pinnedIds)->toContain($pinnedNote->id)
        ->and($pinnedIds)->not->toContain($favNote->id)
        ->and($pinnedIds)->not->toContain($regularNote->id);

    // Request with category=favorites
    $reqFav = Request::create('/api/notes', 'GET', ['category' => 'favorites']);
    $reqFav->headers->set('Accept', 'application/json');
    $resFav = $controller->index($reqFav);
    $favData = $resFav->getData(true)['data'];
    $favIds = array_column($favData, 'id');
    expect($favIds)->toContain($favNote->id)
        ->and($favIds)->not->toContain($pinnedNote->id)
        ->and($favIds)->not->toContain($regularNote->id);
});

test('white-box: note chunk service importMarkdown preserves is_pinned and is_favorite from json payload', function () {
    $service = app(NoteChunkService::class);

    $jsonPayload = json_encode([
        'title' => 'Archmage Grimoire',
        'category' => 'spellbook',
        'content' => '<h2>Grand Magic</h2><p>Summon celestial phoenix.</p>',
        'font_family' => 'pixel',
        'font_size' => '18px',
        'theme_color' => 'purple',
        'is_pinned' => true,
        'is_favorite' => true,
    ]);

    $note = $service->importMarkdown($jsonPayload);

    expect($note->title)->toBe('Archmage Grimoire')
        ->and($note->category)->toBe('spellbook')
        ->and($note->is_pinned)->toBeTrue()
        ->and($note->is_favorite)->toBeTrue()
        ->and($note->chunks()->count())->toBeGreaterThan(0);
});

test('white-box: category controller allocateSlug prevents collision with virtual filter slugs', function () {
    $controller = app(CategoryController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('allocateSlug');
    $method->setAccessible(true);

    $pinnedSlug = $method->invoke($controller, 'pinned');
    expect($pinnedSlug)->toBe('pinned-2');

    $favoritesSlug = $method->invoke($controller, 'favorites');
    expect($favoritesSlug)->toBe('favorites-2');

    $allSlug = $method->invoke($controller, 'all');
    expect($allSlug)->toBe('all-2');
});

test('white-box: note controller export generates valid csv with metadata and plain text content', function () {
    $note = Note::factory()->create([
        'title' => 'Ancient Alchemy Recipes',
        'category' => 'spellbook',
        'content' => '<h2>Potion of Invisibility</h2><p>Mix moonflower and stardust.</p>',
        'is_pinned' => true,
        'is_favorite' => true,
        'word_count' => 8,
        'char_count' => 45,
    ]);

    $controller = app(NoteController::class);
    $response = $controller->export($note, 'csv');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('ancient-alchemy-recipes.csv');

    $content = $response->getContent();
    expect($content)->toContain('Title,Category,Level,XP,Words,Chars,Pinned,Favorite,Created,Updated,Content')
        ->and($content)->toContain('Ancient Alchemy Recipes')
        ->and($content)->toContain('spellbook')
        ->and($content)->toContain('Yes,Yes');
});

test('white-box: category controller index seeds default categories when database has no categories', function () {
    // Delete all categories directly to test auto-seeding
    Category::query()->delete();
    expect(Category::count())->toBe(0);

    $controller = app(CategoryController::class);
    $request = Request::create('/api/categories', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $controller->index($request);
    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['status'])->toBe('success')
        ->and(count($data['data']))->toBeGreaterThan(0)
        ->and(Category::where('is_system', true)->count())->toBeGreaterThan(0);
});

test('white-box: category controller calculatePlayerStats includes pinned and favorite telemetry counts', function () {
    Note::factory()->create(['is_pinned' => true, 'is_favorite' => false]);
    Note::factory()->create(['is_pinned' => true, 'is_favorite' => true]);
    Note::factory()->create(['is_pinned' => false, 'is_favorite' => false]);

    $controller = app(CategoryController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('calculatePlayerStats');
    $method->setAccessible(true);

    $stats = $method->invoke($controller);
    expect($stats)->toHaveKey('pinned_notes')
        ->and($stats)->toHaveKey('favorite_notes')
        ->and($stats['pinned_notes'])->toBeGreaterThanOrEqual(2)
        ->and($stats['favorite_notes'])->toBeGreaterThanOrEqual(1);
});

test('white-box: note content sanitizer preserves safe link targets, rels, and disabled checkboxes', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $html = '<p><a href="https://notenux.dev" target="_blank" rel="noopener noreferrer">Grimoire Link</a></p>'
        .'<p><input type="checkbox" checked disabled> Completed Quest</p>'
        .'<p><input type="text" value="malicious"></p>'
        .'<script>alert("hack")</script>';

    $clean = $sanitizer->sanitize($html);

    expect($clean)->toContain('href="https://notenux.dev"')
        ->and($clean)->toContain('target="_blank"')
        ->and($clean)->toContain('rel="noopener noreferrer"')
        ->and($clean)->toContain('type="checkbox"')
        ->and($clean)->toContain('checked')
        ->and($clean)->toContain('disabled')
        ->and($clean)->not->toContain('<script>')
        ->and($clean)->not->toContain('type="text"');
});

test('white-box: note content sanitizer preserves figure, figcaption, data-align, and alignment styles while stripping xss', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $html = '<figure class="retro-image-figure retro-align-center text-center my-3" data-align="center" style="text-align: center; margin-left: auto; margin-right: auto;" onmouseover="alert(1)">'
        .'<img src="https://notenux.dev/artifact.png" alt="Crystal" onerror="steal()">'
        .'<figcaption class="retro-image-caption" contenteditable="true" data-placeholder="Tambahkan caption gambar..." onclick="hack()">Ancient Mana Crystal</figcaption>'
        .'</figure>';

    $clean = $sanitizer->sanitize($html);

    expect($clean)->toContain('<figure')
        ->and($clean)->toContain('retro-align-center')
        ->and($clean)->toContain('data-align="center"')
        ->and($clean)->toContain('text-align: center')
        ->and($clean)->toContain('margin-left: auto')
        ->and($clean)->toContain('margin-right: auto')
        ->and($clean)->toContain('<img')
        ->and($clean)->toContain('https://notenux.dev/artifact.png')
        ->and($clean)->toContain('<figcaption')
        ->and($clean)->toContain('contenteditable="true"')
        ->and($clean)->toContain('data-placeholder="Tambahkan caption gambar..."')
        ->and($clean)->toContain('Ancient Mana Crystal')
        ->and($clean)->not->toContain('onmouseover')
        ->and($clean)->not->toContain('onerror')
        ->and($clean)->not->toContain('onclick')
        ->and($clean)->not->toContain('alert(1)');
});

test('white-box: note controller exports figure with caption to markdown and plain text accurately', function () {
    $controller = app(NoteController::class);

    $html = '<figure class="retro-image-figure retro-align-right" data-align="right">'
        .'<img src="https://notenux.dev/blade.png" alt="Legendary Blade">'
        .'<figcaption class="retro-image-caption">Bilah Cahaya Bintang</figcaption>'
        .'</figure>';

    $md = $controller->convertToMarkdown($html);
    expect($md)->toContain('![Legendary Blade](https://notenux.dev/blade.png)')
        ->and($md)->toContain('*Bilah Cahaya Bintang*');

    $plain = $controller->convertToPlainText($html);
    expect($plain)->toContain('Bilah Cahaya Bintang')
        ->and($plain)->not->toContain('Tambahkan caption gambar...');
});

test('white-box: note controller bulkUpdate updates pinned, favorite, and category flags atomically', function () {
    $note1 = Note::factory()->create(['is_pinned' => false, 'is_favorite' => false, 'category' => 'quest']);
    $note2 = Note::factory()->create(['is_pinned' => false, 'is_favorite' => false, 'category' => 'quest']);

    $controller = app(NoteController::class);
    $request = Request::create('/api/notes/bulk-update', 'POST', [
        'ids' => [$note1->id, $note2->id],
        'is_pinned' => true,
        'is_favorite' => true,
        'category' => 'spellbook',
    ]);

    $response = $controller->bulkUpdate($request);
    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);
    expect($data['status'])->toBe('success')
        ->and($data['data']['updated_count'])->toBe(2);

    $note1->refresh();
    $note2->refresh();

    expect($note1->is_pinned)->toBeTrue()
        ->and($note1->is_favorite)->toBeTrue()
        ->and($note1->category)->toBe('spellbook')
        ->and($note2->is_pinned)->toBeTrue()
        ->and($note2->is_favorite)->toBeTrue()
        ->and($note2->category)->toBe('spellbook');
});

test('white-box: note controller bulkUpdate validates request payload and handles empty updates safely', function () {
    $note = Note::factory()->create();
    $controller = app(NoteController::class);

    // Empty updates payload
    $reqEmpty = Request::create('/api/notes/bulk-update', 'POST', [
        'ids' => [$note->id],
    ]);
    $resEmpty = $controller->bulkUpdate($reqEmpty);
    expect($resEmpty->getStatusCode())->toBe(200);
    $dataEmpty = $resEmpty->getData(true);
    expect($dataEmpty['status'])->toBe('success')
        ->and($dataEmpty['data']['updated_count'])->toBe(0);
});

test('white-box: note model scopeForUser and relationship isolate notes by user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $note1 = Note::factory()->forUser($user1)->create(['title' => 'User1 Note']);
    $note2 = Note::factory()->forUser($user2)->create(['title' => 'User2 Note']);

    $user1Notes = Note::forUser($user1)->get();
    expect($user1Notes->pluck('id'))->toContain($note1->id)
        ->and($user1Notes->pluck('id'))->not->toContain($note2->id);

    $user2Notes = Note::forUser($user2)->get();
    expect($user2Notes->pluck('id'))->toContain($note2->id)
        ->and($user2Notes->pluck('id'))->not->toContain($note1->id);

    expect($user1->notes->pluck('id'))->toContain($note1->id)
        ->and($note1->user->id)->toBe($user1->id);
});

test('white-box: note controller authorizeNote aborts with 403 on tenant mismatch', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $note = Note::factory()->forUser($user1)->create();

    $controller = app(NoteController::class);
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('authorizeNote');
    $method->setAccessible(true);

    // Same user: passes without exception
    $method->invoke($controller, $note, $user1);
    expect(true)->toBeTrue();

    // Different user: throws 403
    try {
        $method->invoke($controller, $note, $user2);
        $this->fail('Expected 403 HttpException was not thrown.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
});

test('white-box: note chunk service importMarkdown assigns user_id when provided', function () {
    $user = User::factory()->create();
    $service = app(NoteChunkService::class);

    $markdown = "# Arcane Chronicles\n\nChapter 1 text here.";
    $note = $service->importMarkdown($markdown, 'chronicles.md', 'lore', $user);

    expect($note->user_id)->toBe($user->id)
        ->and($note->title)->toBe('Arcane Chronicles');
});

test('white-box: note controller bulkUpdate handles font_family, font_size, and theme_color', function () {
    $user = User::factory()->create();
    $note = Note::factory()->forUser($user)->create([
        'font_family' => 'pixel',
        'font_size' => '16px',
        'theme_color' => 'blue',
    ]);

    $controller = app(NoteController::class);
    $request = Request::create('/api/notes/bulk-update', 'POST', [
        'ids' => [$note->id],
        'font_family' => 'mono',
        'font_size' => '14px',
        'theme_color' => 'emerald',
    ]);
    $request->setUserResolver(fn () => $user);

    $res = $controller->bulkUpdate($request);
    $data = $res->getData(true);

    expect($data['status'])->toBe('success')
        ->and($data['data']['updated_count'])->toBe(1)
        ->and($note->fresh()->font_family)->toBe('mono')
        ->and($note->fresh()->font_size)->toBe('14px')
        ->and($note->fresh()->theme_color)->toBe('emerald');
});

test('white-box: database restore command recovers database state from json backup', function () {
    $user = User::factory()->create(['name' => 'Original Scribe']);
    $note = Note::factory()->forUser($user)->create(['title' => 'Original Scroll Before Restore']);

    // Run backup
    $this->artisan('db:backup', ['--file' => 'white_box_restore_test'])->assertSuccessful();

    // Mutate state
    $note->update(['title' => 'Tampered Title']);
    expect($note->fresh()->title)->toBe('Tampered Title');

    // Run restore
    $this->artisan('db:restore', ['file' => 'white_box_restore_test.json'])->assertSuccessful();

    // Verify restored
    $restoredNote = Note::find($note->id);
    expect($restoredNote)->not->toBeNull()
        ->and($restoredNote->title)->toBe('Original Scroll Before Restore');

    $backupDir = database_path('backups');
    @unlink("{$backupDir}/white_box_restore_test.json");
    @unlink("{$backupDir}/white_box_restore_test.sql");
});

test('white-box: note chunk service detects and isolates corrupted chunks upon cryptographic audit', function () {
    $user = User::factory()->create();
    $note = Note::factory()->forUser($user)->create();
    $service = app(NoteChunkService::class);

    $service->syncChunks($note, '<p>First safe paragraph.</p><p>Second secure paragraph.</p>');
    $reportInitial = $service->verifyIntegrity($note);
    expect($reportInitial['status'])->toBe('verified')
        ->and($reportInitial['verified'])->toBeTrue()
        ->and($reportInitial['corrupted_chunks'])->toBe(0);

    // Intentionally corrupt a chunk's content in database without updating checksum
    $chunk = $note->chunks()->first();
    $chunk->updateQuietly(['content' => '<p>Tampered illegal text.</p>']);

    $reportCorrupted = $service->verifyIntegrity($note);
    expect($reportCorrupted['status'])->toBe('corrupted')
        ->and($reportCorrupted['verified'])->toBeFalse()
        ->and($reportCorrupted['corrupted_chunks'])->toBe(1);
});
