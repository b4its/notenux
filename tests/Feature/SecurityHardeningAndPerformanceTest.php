<?php

use App\Models\Folder;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteChunkService;
use App\Services\NoteContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('security: responses include hardened security headers and omit x-powered-by', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-XSS-Protection', '1; mode=block')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), accelerometer=(), gyroscope=(), magnetometer=(), usb=()');

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

    expect($clean)->toContain('rel="noopener noreferrer"');
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

test('security: csp policy enforces base-uri self, object-src none, and upgrade-insecure-requests', function () {
    $response = $this->get('/');
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("base-uri 'self'")
        ->and($csp)->toContain("object-src 'none'")
        ->and($csp)->toContain('upgrade-insecure-requests');
});

test('security: csv export neutralizes formula triggers with leading whitespace and pipe symbols', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => '   =cmd|\' /C calc\'!A0',
        'category' => ' |123456789',
        'content' => '<p>  -DangerousFormulaWithSpaces</p>',
    ]);

    $response = $this->actingAs($user)->get("/api/notes/{$note->id}/export/csv");
    $response->assertOk();

    $csv = $response->getContent();
    expect($csv)->toContain("'   =cmd|' /C calc'!A0")
        ->and($csv)->toContain("' |123456789")
        ->and($csv)->toContain("'-DangerousFormulaWithSpaces");
});

test('security: html export attaches strict content security policy meta tag', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create([
        'user_id' => $user->id,
        'title' => 'Secure Export Scroll',
        'content' => '<p>Sanitized content</p>',
    ]);

    $response = $this->actingAs($user)->get("/api/notes/{$note->id}/export/html");
    $response->assertOk();

    $html = $response->getContent();
    expect($html)->toContain('<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data: https:;">');
});

test('security: category bulk delete rejects arrays exceeding 100 items', function () {
    $user = User::factory()->create();
    $tooManyIds = range(1, 101);

    $response = $this->actingAs($user)->postJson('/api/categories/bulk-delete', [
        'ids' => $tooManyIds,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

test('performance: composite indexes exist on notes table for user_id, pinned, and category', function () {
    expect(Schema::hasIndex('notes', 'notes_user_id_pinned_updated_index'))->toBeTrue()
        ->and(Schema::hasIndex('notes', 'notes_user_id_category_updated_index'))->toBeTrue();
});

test('security: dom clobbering defense removes reserved system ids from note content', function () {
    $sanitizer = app(NoteContentSanitizer::class);

    $clobberingHtml = '<div id="save-now-btn"><span id="note-title-input">Sneaky</span><p id="section-safe-1">Safe</p></div>';
    $clean = $sanitizer->sanitize($clobberingHtml);

    expect($clean)->not->toContain('id="save-now-btn"')
        ->and($clean)->not->toContain('id="note-title-input"')
        ->and($clean)->toContain('id="section-safe-1"');
});

test('security: note title sanitizes null bytes and control characters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/notes', [
        'title' => "Dangerous\0Null\x07Byte\tTitle",
        'content' => '<p>Normal text</p>',
    ]);

    $response->assertStatus(201);
    $createdNote = Note::find($response->json('data.id'));

    expect($createdNote->title)->not->toContain("\0")
        ->and($createdNote->title)->not->toContain("\x07");
});

test('security: chunk verification uses constant-time hash comparison and flags tampered chunks', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);
    $chunkService = app(NoteChunkService::class);
    $chunkService->syncChunks($note, '<p>Original text shard</p>');

    $verifyRes = $this->actingAs($user)->getJson("/api/notes/{$note->id}/chunks/verify");
    $verifyRes->assertOk()
        ->assertJsonPath('data.status', 'verified');

    // Tamper with chunk checksum
    $chunk = $note->chunks()->first();
    $chunk->update(['checksum' => hash('sha256', 'tampered')]);

    $tamperedRes = $this->actingAs($user)->getJson("/api/notes/{$note->id}/chunks/verify");
    $tamperedRes->assertOk()
        ->assertJsonPath('data.status', 'corrupted');
});

test('performance: landing page does not perform unnecessary queries for private note titles', function () {
    $response = $this->get('/');
    $response->assertOk()
        ->assertViewMissing('sampleNotes');
});

test('security: sensitive dynamic endpoints attach private no-store cache controls', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/player-stats');
    $response->assertOk();
    $cacheHeader = (string) $response->headers->get('Cache-Control');
    expect($cacheHeader)->toContain('no-store')
        ->and($cacheHeader)->toContain('private');

    $studioResponse = $this->actingAs($user)->get('/studio');
    $studioResponse->assertOk();
    $studioCache = (string) $studioResponse->headers->get('Cache-Control');
    expect($studioCache)->toContain('no-store')
        ->and($studioCache)->toContain('private');
});

test('performance: folder allDescendantIds resolves deep hierarchy in a single indexed query', function () {
    $user = User::factory()->create();
    $f1 = Folder::factory()->create(['user_id' => $user->id, 'name' => 'F1']);
    $f2 = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $f1->id, 'name' => 'F2']);
    $f3 = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $f2->id, 'name' => 'F3']);
    $f4 = Folder::factory()->create(['user_id' => $user->id, 'parent_id' => $f3->id, 'name' => 'F4']);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $descendants = $f1->allDescendantIds();

    $queryCount = count(DB::getQueryLog());
    expect($queryCount)->toBe(1)
        ->and($descendants)->toContain($f2->id, $f3->id, $f4->id);
});

test('security: ai assistant generic exception hides internal server trace and returns 502 with sanitized message', function () {
    config(['services.ai.key' => 'secret-test', 'services.ai.model' => 'test-model', 'services.ai.base_url' => 'https://ai.example/v1']);
    Http::preventStrayRequests();
    Http::fake(function () {
        throw new RuntimeException('Database server /var/run/mysqld.sock connection refused on internal IP 10.0.0.5');
    });

    $note = Note::factory()->create();
    $response = $this->postJson(route('notes.assistant', $note), ['action' => 'improve', 'content' => 'Draft']);

    $response->assertStatus(502)
        ->assertJsonPath('status', 'error')
        ->assertJsonPath('message', 'Layanan AI mengalami kendala internal. Silakan coba lagi nanti.')
        ->assertDontSee('10.0.0.5')
        ->assertDontSee('mysqld.sock');
});

test('performance: markdown import and json import default typography to sans', function () {
    $user = User::factory()->create();
    $service = app(NoteChunkService::class);

    $mdNote = $service->importMarkdown("# Clean Typography\n\nDefault font test.", 'clean.md', 'catatan', $user);
    expect($mdNote->font_family)->toBe('sans');

    $jsonNote = $service->importMarkdown(json_encode([
        'title' => 'JSON Typography Test',
        'content' => '<p>Test</p>',
    ]), null, 'catatan', $user);
    expect($jsonNote->font_family)->toBe('sans');
});

test('security: security headers include x-permitted-cross-domain-policies none and remove server header', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');

    expect($response->headers->has('Server'))->toBeFalse();
});

test('performance: new composite indexes exist for folders, categories, and note words', function () {
    expect(Schema::hasIndex('folders', 'folders_user_id_name_index'))->toBeTrue()
        ->and(Schema::hasIndex('categories', 'categories_is_system_user_name_index'))->toBeTrue()
        ->and(Schema::hasIndex('notes', 'notes_user_pinned_words_index'))->toBeTrue();
});

test('security: profile password change re-authenticates and regenerates session id', function () {
    $user = User::factory()->create([
        'password' => bcrypt('old-password'),
    ]);

    $this->actingAs($user);
    $sessionIdBefore = session()->getId();

    $response = $this->putJson('/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'current_password' => 'old-password',
        'password' => 'new-secure-password-123',
        'password_confirmation' => 'new-secure-password-123',
    ]);

    $response->assertOk();
    $sessionIdAfter = session()->getId();

    expect($sessionIdAfter)->not->toBe($sessionIdBefore);
});

test('performance: database backup command includes folders table in backup payload', function () {
    $user = User::factory()->create();
    Folder::factory()->create(['user_id' => $user->id, 'name' => 'Chronicles']);

    $testBackupFile = 'test_folders_backup_'.time();
    $this->artisan("db:backup --file={$testBackupFile}")->assertSuccessful();

    $backupPath = database_path("backups/{$testBackupFile}.json");
    expect(file_exists($backupPath))->toBeTrue();

    $content = json_decode(file_get_contents($backupPath), true);
    expect(isset($content['tables']['folders']))->toBeTrue()
        ->and($content['tables']['folders']['count'])->toBeGreaterThanOrEqual(1);

    // Clean up temporary test backup files
    @unlink($backupPath);
    @unlink(database_path("backups/{$testBackupFile}.sql"));
});

test('performance: sitemap route serves xml with public daily cache header and validates file presence', function () {
    $response = $this->get('/sitemap.xml');
    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $cacheHeader = (string) $response->headers->get('Cache-Control');
    expect($cacheHeader)->toContain('public')
        ->and($cacheHeader)->toContain('max-age=86400');
});

test('security: bulk operations reject duplicate ids in payload using distinct validation rule', function () {
    $user = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/notes/bulk-delete', [
        'ids' => [$note->id, $note->id],
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('ids.0');
});
