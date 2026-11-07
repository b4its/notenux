<?php

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('black-box: assistant previews each action without modifying the manuscript', function (string $action) {
    config(['services.ai.key' => 'secret-test', 'services.ai.model' => 'test-model', 'services.ai.base_url' => 'https://ai.example/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://ai.example/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '# Hasil'], 'finish_reason' => 'stop']]])]);
    $note = Note::factory()->create(['content' => 'Original']);

    $this->postJson(route('notes.assistant', $note), ['action' => $action, 'instruction' => 'Bahasa Indonesia', 'content' => 'Unsaved latest draft'])
        ->assertOk()->assertJsonPath('data.content', '# Hasil')->assertJsonPath('data.note_id', $note->id);

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'content' => 'Original']);
    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret-test')
        && $request['model'] === 'test-model' && str_contains($request['messages'][1]['content'], 'Unsaved latest draft'));
})->with(['improve', 'summarize', 'continue', 'translate', 'custom']);

test('black-box: unconfigured assistant returns actionable error without outbound requests', function () {
    config(['services.ai.key' => null]);
    Http::preventStrayRequests();
    $note = Note::factory()->create();

    $this->postJson(route('notes.assistant', $note), ['action' => 'improve', 'content' => 'Draft'])
        ->assertStatus(503)->assertJsonPath('message', 'AI assistant belum dikonfigurasi. Atur AI_API_KEY dan AI_MODEL di server.');

    Http::assertNothingSent();
});

test('black-box: assistant rejects invalid inputs before contacting provider', function (array $payload, string $field) {
    Http::preventStrayRequests();
    $note = Note::factory()->create();

    $this->postJson(route('notes.assistant', $note), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

    Http::assertNothingSent();
})->with([
    'missing' => [[], 'action'],
    'unknown action' => [['action' => 'delete', 'content' => 'Draft'], 'action'],
    'empty content' => [['action' => 'improve', 'content' => ''], 'content'],
    'long content' => [['action' => 'improve', 'content' => str_repeat('x', 30001)], 'content'],
    'translation language' => [['action' => 'translate', 'content' => 'Draft'], 'instruction'],
    'custom instruction' => [['action' => 'custom', 'content' => 'Draft'], 'instruction'],
]);

test('black-box: provider errors never expose secrets or overwrite the manuscript', function (int $code, array $body) {
    config(['services.ai.key' => 'secret-test', 'services.ai.model' => 'test-model', 'services.ai.base_url' => 'https://ai.example/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://ai.example/v1/chat/completions' => Http::response($body, $code)]);
    $note = Note::factory()->create(['content' => 'Original']);

    $this->postJson(route('notes.assistant', $note), ['action' => 'improve', 'content' => 'Draft'])
        ->assertStatus(502)->assertDontSee('secret-test');

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'content' => 'Original']);
    Http::assertSentCount(1);
})->with([
    'unauthorized' => [401, ['error' => 'secret-test']],
    'rate limited' => [429, []],
    'unavailable' => [500, []],
    'malformed' => [200, ['unexpected' => true]],
    'empty' => [200, ['choices' => [['message' => ['content' => ' ']]]]],
    'truncated' => [200, ['choices' => [['message' => ['content' => 'Partial'], 'finish_reason' => 'length']]]],
]);

test('black-box: assistant handles connection failures gracefully', function () {
    config(['services.ai.key' => 'secret-test', 'services.ai.model' => 'test-model', 'services.ai.base_url' => 'https://ai.example/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://ai.example/v1/chat/completions' => Http::failedConnection()]);
    $note = Note::factory()->create();

    $this->postJson(route('notes.assistant', $note), ['action' => 'improve', 'content' => 'Draft'])->assertStatus(504);

    Http::assertSentCount(1);
});
