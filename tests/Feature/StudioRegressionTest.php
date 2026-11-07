<?php

use App\Models\Note;
use App\Models\User;
use App\Services\NoteChunkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('white-box: plain text chunk reassembly preserves every separator and unicode byte', function (string $separator) {
    $service = app(NoteChunkService::class);
    $content = str_repeat('écriture 日本語 ', 100).$separator.'Bab kedua'.$separator.'Penutup';
    $note = Note::factory()->create();

    $service->syncChunks($note, $content);

    expect($service->reassemble($note))->toBe($content);
})->with(["\n\n", "\r\n\r\n", "\n\n\n"]);

test('black-box: rich content is sanitized while tables and checked tasks survive', function () {
    $response = $this->postJson(route('notes.store'), [
        'title' => 'Safe manuscript',
        'content' => '<p onclick="alert(1)">Hello</p><script>alert(1)</script><img src="https://example.com/a.png" onerror="alert(1)"><a href="javascript:alert(1)">link</a><ul class="task-list"><li><input type="checkbox" checked>Done</li></ul><table><tr><td>Data</td></tr></table>',
    ]);

    $response->assertCreated();
    $content = $response->json('data.content');
    expect($content)->not->toContain('onclick', '<script', 'onerror', 'javascript:')
        ->toContain('<table>', 'type="checkbox"', 'checked', 'Hello');
    $this->assertDatabaseHas('notes', ['id' => $response->json('data.id'), 'content' => $content]);
});

test('black-box: clearing content also clears chunks and metrics', function () {
    $note = Note::factory()->create();
    app(NoteChunkService::class)->syncChunks($note, '<p>Old text</p>');

    $this->putJson(route('notes.update', $note), ['content' => null])->assertOk()
        ->assertJsonPath('data.content', '')->assertJsonPath('data.word_count', 0);

    expect($note->fresh()->chunks->pluck('content')->all())->toBe(['']);
});

test('black-box: invalid chunk orders do not mutate a manuscript', function (string $invalid) {
    $note = Note::factory()->create();
    $service = app(NoteChunkService::class);
    $service->syncChunks($note, str_repeat('First ', 250)."\n\n".str_repeat('Second ', 250));
    $ids = $note->chunks->pluck('id')->all();
    $before = $note->fresh()->content;
    $order = match ($invalid) {
        'duplicate' => [$ids[0], $ids[0]],
        'partial' => [$ids[0]],
        'foreign' => [999999, $ids[0]],
        'nested' => [[$ids[0]]],
        'alias duplicate' => [$ids[0], $note->chunks->first()->chunk_uid],
    };

    $this->postJson(route('notes.chunks.reorder', $note), ['order' => $order])
        ->assertUnprocessable()->assertJsonValidationErrors($invalid === 'nested' ? 'order.0' : 'order');

    expect($note->fresh()->content)->toBe($before);
    expect($note->chunks()->pluck('id')->all())->toBe($ids);
})->with(['duplicate', 'partial', 'foreign', 'nested', 'alias duplicate']);

test('black-box: foreign chunks cannot be changed or deleted', function () {
    $note = Note::factory()->create();
    $other = Note::factory()->create();
    $chunk = app(NoteChunkService::class)->syncChunks($other, 'Private text')[0];

    $this->putJson(route('notes.chunks.update', [$note, $chunk]), ['content' => 'Changed'])->assertNotFound();
    $this->deleteJson(route('notes.chunks.delete', [$note, $chunk]))->assertNotFound();

    $this->assertDatabaseHas('note_chunks', ['id' => $chunk->id, 'content' => 'Private text']);
});

test('white-box: metrics separate html blocks and count non ascii words', function () {
    $note = Note::factory()->create();

    app(NoteChunkService::class)->syncChunks($note, '<p>Halo dunia</p><p>café 日本語 &amp; cerita</p>');

    expect($note->fresh()->word_count)->toBe(5);
});

test('white-box: rich chunks never split inside nested elements or table rows', function () {
    $content = '<div><p>'.str_repeat('Nested text ', 150).'</p></div><table><tr><td>'.str_repeat('Cell ', 300).'</td></tr></table>';
    $service = app(NoteChunkService::class);

    $chunks = $service->splitIntoChunks($content);

    expect($chunks)->toHaveCount(2);
    expect($chunks[0]['content'])->toEndWith('</div>');
    expect($chunks[1]['content'])->toStartWith('<table>')->toEndWith('</table>');
    expect(implode('', array_column($chunks, 'content')))->toBe($content);
});

test('black-box: legacy content and export titles cannot execute scripts', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['title' => '</script><script>alert(99)</script>', 'content' => '<p>Safe</p><img src=x onerror="alert(99)">']);

    $this->actingAs($user)->get(route('studio'))->assertOk()->assertDontSee('<script>alert(99)</script>', false)->assertDontSee('onerror=', false);
    $this->get(route('notes.export', [$note, 'html']))->assertOk()
        ->assertDontSee('<script>alert(99)</script>', false)->assertDontSee('onerror=', false);
});

test('black-box: markdown import strips raw html and unsafe links', function () {
    $response = $this->postJson(route('notes.import'), ['content' => "# Safe\n\n<script>alert(1)</script>\n\n[link](javascript:alert(1))"]);

    $response->assertCreated();
    expect($response->json('data.content'))->not->toContain('<script', 'javascript:');
});

test('black-box: api endpoints accept writes with valid payload', function () {
    $this->postJson(route('notes.store'), ['title' => 'Legitimate'])->assertCreated();
    $this->assertDatabaseHas('notes', ['title' => 'Legitimate']);
});

test('black-box: duplicating the maximum title length remains within storage limits', function () {
    $note = Note::factory()->create(['title' => str_repeat('字', 255)]);

    $response = $this->postJson(route('notes.duplicate', $note));

    $response->assertCreated();
    expect(mb_strlen($response->json('data.title')))->toBe(255);
});

test('white-box: failed shard creation rolls back the new manuscript', function () {
    Event::listen('eloquent.creating: App\\Models\\NoteChunk', function () {
        throw new RuntimeException('Simulated storage failure');
    });

    $this->postJson(route('notes.store'), ['title' => 'Atomic note', 'content' => '<p>Draft</p>'])->assertServerError();

    $this->assertDatabaseCount('notes', 0);
    $this->assertDatabaseCount('note_chunks', 0);
});

test('white-box: failed resynchronization restores the previous manuscript and shards', function () {
    $note = Note::factory()->create(['title' => 'Original']);
    $chunk = app(NoteChunkService::class)->syncChunks($note, '<p>Original</p>')[0];
    Event::listen('eloquent.creating: App\\Models\\NoteChunk', function () {
        throw new RuntimeException('Simulated storage failure');
    });

    $this->putJson(route('notes.update', $note), ['title' => 'Changed', 'content' => '<p>Changed</p>'])->assertServerError();

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'title' => 'Original', 'content' => '<p>Original</p>']);
    $this->assertDatabaseHas('note_chunks', ['id' => $chunk->id, 'content' => '<p>Original</p>']);
});

test('black-box: assistant rate limiting caps outbound paid requests', function () {
    config(['services.ai.key' => 'test-key', 'services.ai.model' => 'test-model', 'services.ai.base_url' => 'https://ai.example/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://ai.example/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Result']]]])]);
    $note = Note::factory()->create();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson(route('notes.assistant', $note), ['action' => 'improve', 'content' => 'Draft'])->assertOk();
    }
    $this->postJson(route('notes.assistant', $note), ['action' => 'improve', 'content' => 'Draft'])->assertTooManyRequests();

    Http::assertSentCount(10);
});
