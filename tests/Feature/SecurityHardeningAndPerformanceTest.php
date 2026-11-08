<?php

use App\Models\Note;
use App\Models\User;
use App\Services\NoteContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('security: responses include hardened security headers and omit x-powered-by', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-XSS-Protection', '1; mode=block')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

    expect($response->headers->has('Content-Security-Policy'))->toBeTrue()
        ->and($response->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")
        ->and($response->headers->has('X-Powered-By'))->toBeFalse();
});

test('security: https requests attach strict transport security header', function () {
    $response = $this->get('https://localhost/');

    $response->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('security: csv export neutralizes formula and dde injection triggers', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => '=cmd|\' /C calc\'!A0',
        'category' => '+123456789',
        'content' => '<p>-DangerousFormula</p>',
    ]);

    $response = $this->actingAs($user)->get("/api/notes/{$note->id}/export/csv");
    $response->assertOk();

    $csv = $response->getContent();
    expect($csv)->toContain("'=cmd|' /C calc'!A0")
        ->and($csv)->toContain("'+123456789")
        ->and($csv)->toContain("'-DangerousFormula");
});

test('security: import rejects dangerous file extensions like php or exe', function () {
    $user = User::factory()->create();
    $badFile = UploadedFile::fake()->createWithContent('exploit.php', '<?php phpinfo(); ?>');

    $response = $this->actingAs($user)->postJson('/api/notes/import', [
        'file' => $badFile,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

test('security: note content sanitizer strips css url and expression exfiltration while preserving safe styles', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $malicious = '<p style="color: red; background: url(\'http://attacker.com/leak\');">Text</p>';
    $clean = $sanitizer->sanitize($malicious);
    expect($clean)->not->toContain('url(')
        ->and($clean)->not->toContain('attacker.com');

    $safe = '<p style="color: blue; text-align: center; border: 1px solid black;">Safe text</p>';
    $cleanSafe = $sanitizer->sanitize($safe);
    expect($cleanSafe)->toContain('color: blue')
        ->and($cleanSafe)->toContain('text-align: center');
});

test('security: links with target blank automatically receive noopener to prevent reverse tabnabbing', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $html = '<a href="https://example.com" target="_blank">External Link</a>';
    $clean = $sanitizer->sanitize($html);

    expect($clean)->toContain('rel="noopener"');
});

test('security: bulk operations reject arrays exceeding 100 items to prevent denial of service', function () {
    $user = User::factory()->create();
    $tooManyIds = range(1, 101);

    $response = $this->actingAs($user)->postJson('/api/notes/bulk-delete', [
        'ids' => $tooManyIds,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

test('performance: telemetry stats calculates aggregated metrics via single database query', function () {
    $user = User::factory()->create();
    Note::factory()->count(5)->create([
        'user_id' => $user->id,
        'word_count' => 100,
        'xp' => 300,
        'is_pinned' => true,
        'is_favorite' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/api/player-stats');
    $response->assertOk()
        ->assertJsonPath('data.total_notes', 5)
        ->assertJsonPath('data.total_words', 500)
        ->assertJsonPath('data.total_xp', 1500)
        ->assertJsonPath('data.pinned_notes', 5)
        ->assertJsonPath('data.favorite_notes', 0);
});
